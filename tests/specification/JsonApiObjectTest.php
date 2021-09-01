<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

/**
 * @see https://jsonapi.org/format/1.1/#document-jsonapi-object
 */
class JsonApiObjectTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');

        $this->api->resourceType('articles', new MockAdapter());
    }

    public function test_document_includes_jsonapi_member_with_version_1_1()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')
        );

        $this->assertJsonApiDocumentSubset([
            'jsonapi' => [
                'version' => '1.1',
            ],
        ], $response->getBody());
    }
}
