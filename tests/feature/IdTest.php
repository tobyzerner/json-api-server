<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
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
}
