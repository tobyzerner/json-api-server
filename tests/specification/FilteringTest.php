<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/#fetching-filtering
 */
class FilteringTest extends AbstractTestCase
{
    public function test_filter_query_parameter_is_available_to_implementations(): void
    {
        $api = new JsonApi();

        $api->resource(
            new MockResource(
                'users',
                models: [
                    (object) ['id' => '1', 'name' => 'Toby'],
                    (object) ['id' => '2', 'name' => 'Franz'],
                ],
                endpoints: [Index::make()],
                fields: [Attribute::make('name')],
                filters: [
                    CustomFilter::make('name', function ($query, string $value): void {
                        $query->models = array_filter(
                            $query->models,
                            fn($model) => $model->name === $value,
                        );
                    }),
                ],
            ),
        );

        $response = $api->handle($this->buildRequest('GET', '/users?filter[name]=Toby'));

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
}
