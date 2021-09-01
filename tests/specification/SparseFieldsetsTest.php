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
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

/**
 * @see https://jsonapi.org/format/1.1/#fetching-sparse-fieldsets
 */
class SparseFieldsetsTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');

        $articlesAdapter = new MockAdapter([
            '1' => (object) [
                'id' => '1',
                'title' => 'foo',
                'body' => 'bar',
                'user' => (object) [
                    'id' => '1',
                    'firstName' => 'Toby',
                    'lastName' => 'Zerner',
                ],
            ],
        ]);

        $this->api->resourceType('articles', $articlesAdapter, function (Type $type) {
            $type->attribute('title');
            $type->attribute('body');
            $type->hasOne('user')->includable();
        });

        $this->api->resourceType('users', new MockAdapter(), function (Type $type) {
            $type->attribute('firstName');
            $type->attribute('lastName');
        });
    }

    public function test_can_request_sparse_fieldsets()
    {
        $request = $this->api->handle(
            $this->buildRequest('GET', '/articles/1')
                ->withQueryParams([
                    'include' => 'user',
                    'fields' => [
                        'articles' => 'title,user',
                        'users' => 'firstName',
                    ],
                ])
        );

        $document = json_decode($request->getBody(), true);

        $article = $document['data']['attributes'] ?? [];
        $user = $document['included'][0]['attributes'] ?? [];

        $this->assertArrayHasKey('title', $article);
        $this->assertArrayNotHasKey('body', $article);
        $this->assertArrayHasKey('firstName', $user);
        $this->assertArrayNotHasKey('lastName', $user);
    }
}
