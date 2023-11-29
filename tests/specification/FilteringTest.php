<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\Field\Str;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

use function Tobyz\JsonApiServer\apply_filters;

/**
 * @see https://jsonapi.org/format/#fetching-filtering
 */
class FilteringTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    ($user1 = (object) ['id' => '1', 'name' => 'Toby']),
                    ($user2 = (object) ['id' => '2', 'name' => 'Franz']),
                ],
                endpoints: [Index::make()],
                fields: [Str::make('name')],
                filters: [
                    CustomFilter::make('name', function ($query, $value) {
                        $query->models = array_filter(
                            $query->models,
                            fn($model) => $model->name === $value,
                        );
                    }),
                ],
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [
                    '1' => (object) [
                        'id' => '1',
                        'author' => $user1,
                    ],
                    '2' => (object) [
                        'id' => '2',
                        'author' => $user2,
                    ],
                ],
                endpoints: [Index::make()],
                fields: [ToOne::make('author')->type('users')],
                filters: [
                    CustomFilter::make('author', function ($query, $value, Context $context) {
                        $query->models = array_filter($query->models, function ($model) use (
                            $query,
                            $value,
                            $context,
                        ) {
                            /** @var ToOne $relationship */
                            $resource = $context->resource(
                                $context->collection->resource($model, $context),
                            );
                            $relationship = $context->fields($resource)['author'];
                            $relatedResource = $context->api->getResource(
                                $relationship->collections[0],
                            );

                            apply_filters(
                                $q = (object) ['models' => [$model->author]],
                                $value,
                                $relatedResource,
                                $context,
                            );

                            return !!$q->models;
                        });
                    }),
                ],
            ),
        );
    }

    public function test_filtering()
    {
        $response = $this->api->handle($this->buildRequest('GET', '/users?filter[name]=Toby'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => ['name' => 'Toby'],
                    ],
                ],
            ],
            $body = $response->getBody(),
        );

        $document = json_decode($body, true);

        $this->assertCount(1, $document['data']);
    }

    public function test_nested_filtering()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles?filter[author][name]=Toby'),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => [['type' => 'articles', 'id' => '1']]],
            $body = $response->getBody(),
        );

        $document = json_decode($body, true);

        $this->assertCount(1, $document['data']);
    }
}
