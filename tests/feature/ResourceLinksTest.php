<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class ResourceLinksTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('/api');
    }

    public function test_resource_links_are_included_in_response()
    {
        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1', 'title' => 'Hello World', 'slug' => 'hello-world']],
                endpoints: [Show::make()],
                links: [
                    Link::make('describedby')->get(
                        fn() => 'https://api.example.com/schemas/articles',
                    ),

                    Link::make('canonical')->get(
                        fn($model) => [
                            'href' => "https://example.com/articles/{$model->slug}",
                            'title' => 'View on website',
                            'type' => 'text/html',
                        ],
                    ),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/api/articles/1'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'articles',
                    'id' => '1',
                    'links' => [
                        'describedby' => 'https://api.example.com/schemas/articles',
                        'canonical' => [
                            'href' => 'https://example.com/articles/hello-world',
                            'title' => 'View on website',
                            'type' => 'text/html',
                        ],
                    ],
                ],
            ],
            $response->getBody(),
        );
    }
}
