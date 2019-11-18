<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer\feature;

use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class MetaTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');
    }

    public function test_meta_fields_can_be_added_to_resources_with_a_closure()
    {
        $adapter = new MockAdapter(['1' => (object) ['id' => '1']]);

        $this->api->resource('users', $adapter, function (Type $type) use ($adapter) {
            $type->meta('foo', function ($model, $request) use ($adapter) {
                $this->assertSame($adapter->models['1'], $model);
                $this->assertInstanceOf(ServerRequestInterface::class, $request);
                return 'bar';
            });
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody(), true);

        $this->assertEquals('bar', $document['data']['meta']['foo']);
    }
}
