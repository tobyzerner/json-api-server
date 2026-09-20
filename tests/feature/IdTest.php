<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Id;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class IdTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_serializes_id_to_string()
    {
        $this->api->resource(
            new MockResource('users', models: [(object) ['id' => 1]], endpoints: [Show::make()]),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(['data' => ['id' => '1']], $response->getBody(), true);
    }

    public function test_validates_id()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                id: Id::make()
                    ->writableOnCreate()
                    ->validate(function ($value, $fail) {
                        if ($value !== 'valid') {
                            $fail('Invalid ID');
                        }
                    }),
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        try {
            $this->api->handle(
                $this->buildRequest('POST', '/users')->withParsedBody([
                    'data' => ['type' => 'users', 'id' => '1'],
                ]),
            );
        } catch (UnprocessableEntityException $e) {
            $this->assertEquals('/data/id', $e->errors[0]['source']['pointer'] ?? null);
            throw $e;
        }

        $this->fail('Expected UnprocessableEntityException to be thrown');
    }

    #[DataProvider('sharedModelProvider')]
    public function test_serializes_shared_model_ids_per_resource(
        string $path,
        array $data,
        array $included,
    ) {
        $model = (object) ['id' => 123];

        foreach (['scoped' => 'plain', 'plain' => 'scoped'] as $resourceType => $related) {
            $this->api->resource(
                new MockResource(
                    $resourceType,
                    models: [$model],
                    endpoints: [Show::make()],
                    id: Id::make()->get(
                        fn($model) => ($resourceType === 'scoped' ? '16:' : '') . $model->id,
                    ),
                    fields: [
                        ToOne::make('related')
                            ->type($related)
                            ->get(fn($model) => $model)
                            ->includable(),
                    ],
                ),
            );
        }

        $response = $this->api->handle($this->buildRequest('GET', $path));
        $document = json_decode($response->getBody(), true);

        $this->assertArraySubset($data, $document['data'], true);
        $this->assertCount(count($included), $document['included'] ?? []);
        $this->assertArraySubset($included, $document['included'] ?? [], true);
    }

    public static function sharedModelProvider(): array
    {
        $scoped = ['type' => 'scoped', 'id' => '16:123'];
        $plain = ['type' => 'plain', 'id' => '123'];
        $scopedResource = $scoped + ['relationships' => ['related' => ['data' => $plain]]];
        $plainResource = $plain + ['relationships' => ['related' => ['data' => $scoped]]];

        return [
            'scoped first, linkage' => ['/scoped/123', $scopedResource, []],
            'plain first, linkage' => ['/plain/123', $plainResource, []],
            'scoped first, included' => [
                '/scoped/123?include=related',
                $scopedResource,
                [$plainResource],
            ],
            'plain first, included' => [
                '/plain/123?include=related',
                $plainResource,
                [$scopedResource],
            ],
        ];
    }

    public function test_caches_id_getter_and_serializer_per_resource_and_model_object()
    {
        $gets = $serializations = 0;
        $resource = new MockResource(
            'users',
            id: Id::make()
                ->get(function ($model) use (&$gets) {
                    $gets++;
                    return $model->id;
                })
                ->serialize(function ($value) use (&$serializations) {
                    $serializations++;
                    return "user:$value";
                }),
        );
        $model = (object) ['id' => 123];
        $context = (new Context($this->api, $this->buildRequest('GET', '/users')))
            ->withResource($resource)
            ->withModel($model);

        $this->assertSame('user:123', $context->id($resource, $model));
        $this->assertSame('user:123', (clone $context)->id($resource, $model));
        $this->assertSame(1, $gets);
        $this->assertSame(1, $serializations);

        $otherModel = clone $model;
        $this->assertSame('user:123', $context->withModel($otherModel)->id($resource, $otherModel));
        $this->assertSame(2, $gets);
        $this->assertSame(2, $serializations);

        $otherResource = clone $resource;
        $otherContext = $context->withResource($otherResource);
        $this->assertSame('user:123', $otherContext->id($otherResource, $model));
        $this->assertSame('user:123', $otherContext->id($otherResource, $model));
        $this->assertSame(3, $gets);
        $this->assertSame(3, $serializations);
    }
}
