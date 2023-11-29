<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;
use Tobyz\Tests\JsonApiServer\MockSort;

/**
 * @see https://jsonapi.org/format/1.1/#fetching-sorting
 */
class SortingTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'articles',
                models: [
                    (object) ['id' => '1', 'title' => 'B', 'body' => 'A'],
                    (object) ['id' => '2', 'title' => 'A', 'body' => 'A'],
                    (object) ['id' => '3', 'title' => 'B', 'body' => 'B'],
                ],
                endpoints: [Index::make()],
                fields: [Attribute::make('title'), Attribute::make('body')],
                sorts: [MockSort::make('title'), MockSort::make('body')],
            ),
        );
    }

    public function test_sorting()
    {
        $response = $this->api->handle($this->buildRequest('GET', '/articles?sort=title,-body'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    ['type' => 'articles', 'id' => '2'],
                    ['type' => 'articles', 'id' => '3'],
                    ['type' => 'articles', 'id' => '1'],
                ],
            ],
            $response->getBody(),
        );
    }
}
