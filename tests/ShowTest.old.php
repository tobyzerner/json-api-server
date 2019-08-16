<?php

/*
 * This file is part of JSON-API.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Schema\Type;
use Psr\Http\Message\ServerRequestInterface as Request;

class ShowTest extends AbstractTestCase
{
    public function test_resource_with_no_fields()
    {
        $api = new JsonApi('http://example.com');
        $api->resource('users', new MockAdapter(), function (Type $schema) {
            // no fields
        });

        $request = $this->buildRequest('GET', '/users/1');
        $response = $api->handle($request);

        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '1',
                    'links' => [
                        'self' => 'http://example.com/users/1'
                    ]
                ]
            ],
            json_decode($response->getBody(), true)
        );
    }

    public function test_attributes()
    {
        $adapter = new MockAdapter([
            '1' => (object) [
                'id' => '1',
                'attribute1' => 'value1',
                'property2' => 'value2',
                'property3' => 'value3'
            ]
        ]);

        $request = $this->buildRequest('GET', '/users/1');

        $api = new JsonApi('http://example.com');

        $api->resource('users', $adapter, function (Type $schema) {
            $schema->attribute('attribute1');
            $schema->attribute('attribute2', 'property2');
            $schema->attribute('attribute3')->property('property3');
        });

        $response = $api->handle($request);

        $this->assertArraySubset(
            [
                'attributes' => [
                    'attribute1' => 'value1',
                    'attribute2' => 'value2',
                    'attribute3' => 'value3'
                ]
            ],
            json_decode($response->getBody(), true)['data']
        );
    }

    public function testAttributeGetter()
    {
        $adapter = new MockAdapter([
            '1' => $model = (object) ['id' => '1']
        ]);

        $request = $this->buildRequest('GET', '/users/1');

        $api = new JsonApi('http://example.com');

        $api->resource('users', $adapter, function (Type $schema) use ($model, $request) {
            $schema->attribute('attribute1')
                ->get(function ($arg1, $arg2) use ($model, $request) {
                    $this->assertInstanceOf(Request::class, $arg1);
                    $this->assertEquals($model, $arg2);
                    return 'value1';
                });
        });

        $response = $api->handle($request);

        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertArraySubset(
            [
                'attributes' => [
                    'attribute1' => 'value1'
                ]
            ],
            json_decode($response->getBody(), true)['data']
        );
    }

    public function testAttributeVisibility()
    {
        $adapter = new MockAdapter([
            '1' => $model = (object) ['id' => '1']
        ]);

        $request = $this->buildRequest('GET', '/users/1');

        $api = new JsonApi('http://example.com');
        $api->resource('users', $adapter, function (Type $schema) use ($model, $request) {
            $schema->attribute('visible1');

            $schema->attribute('visible2')->visible();

            $schema->attribute('visible3')->visibleIf(function ($arg1, $arg2) use ($model, $request) {
                $this->assertInstanceOf(Request::class, $arg1);
                $this->assertEquals($model, $arg2);
                return true;
            });

            $schema->attribute('visible4')->hiddenIf(function ($arg1, $arg2) use ($model, $request) {
                $this->assertInstanceOf(Request::class, $arg1);
                $this->assertEquals($model, $arg2);
                return false;
            });

            $schema->attribute('hidden1')->hidden();

            $schema->attribute('hidden2')->visibleIf(function () {
                return false;
            });

            $schema->attribute('hidden3')->hiddenIf(function () {
                return true;
            });
        });

        $response = $api->handle($request);

        $attributes = json_decode($response->getBody(), true)['data']['attributes'];

        $this->assertArrayHasKey('visible1', $attributes);
        $this->assertArrayHasKey('visible2', $attributes);
        $this->assertArrayHasKey('visible3', $attributes);
        $this->assertArrayHasKey('visible4', $attributes);

        $this->assertArrayNotHasKey('hidden1', $attributes);
        $this->assertArrayNotHasKey('hidden2', $attributes);
        $this->assertArrayNotHasKey('hidden3', $attributes);
    }

    public function testHasOneRelationship()
    {
        $phonesAdapter = new MockAdapter([
            '1' => $phone1 = (object) ['id' => '1', 'number' => '8881'],
            '2' => $phone2 = (object) ['id' => '2', 'number' => '8882'],
            '3' => $phone3 = (object) ['id' => '3', 'number' => '8883']
        ]);

        $usersAdapter = new MockAdapter([
            '1' => (object) [
                'id' => '1',
                'phone' => $phone1,
                'property2' => $phone2,
                'property3' => $phone3,
            ]
        ]);

        $request = $this->buildRequest('GET', '/users/1')
            ->withQueryParams(['include' => 'phone,phone2,phone3']);

        $api = new JsonApi('http://example.com');

        $api->resource('users', $usersAdapter, function (Type $schema) {
            $schema->hasOne('phone');

            $schema->hasOne('phone2', 'phones', 'property2');

            $schema->hasOne('phone3')
                ->type('phones')
                ->property('property3');
        });

        $api->resource('phones', $phonesAdapter, function (Type $schema) {
            $schema->attribute('number');
        });

        $response = $api->handle($request);

        $this->assertArraySubset(
            [
                'relationships' => [
                    'phone' => [
                        'data' => ['type' => 'phones', 'id' => '1']
                    ],
                    'phone2' => [
                        'data' => ['type' => 'phones', 'id' => '2']
                    ],
                    'phone3' => [
                        'data' => ['type' => 'phones', 'id' => '3']
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['data']
        );

        $this->assertEquals(
            [
                [
                    'type' => 'phones',
                    'id' => '1',
                    'attributes' => ['number' => '8881'],
                    'links' => [
                        'self' => 'http://example.com/phones/1'
                    ]
                ],
                [
                    'type' => 'phones',
                    'id' => '2',
                    'attributes' => ['number' => '8882'],
                    'links' => [
                        'self' => 'http://example.com/phones/2'
                    ]
                ],
                [
                    'type' => 'phones',
                    'id' => '3',
                    'attributes' => ['number' => '8883'],
                    'links' => [
                        'self' => 'http://example.com/phones/3'
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['included']
        );
    }

    public function testHasOneRelationshipInclusion()
    {
        $phonesAdapter = new MockAdapter([
            '1' => $phone1 = (object) ['id' => '1', 'number' => '8881'],
            '2' => $phone2 = (object) ['id' => '2', 'number' => '8882']
        ]);

        $usersAdapter = new MockAdapter([
            '1' => (object) [
                'id' => '1',
                'phone' => $phone1,
                'property2' => $phone2
            ]
        ]);

        $request = $this->buildRequest('GET', '/users/1')
            ->withQueryParams(['include' => 'phone2']);

        $api = new JsonApi('http://example.com');

        $api->resource('users', $usersAdapter, function (Type $schema) {
            $schema->hasOne('phone');

            $schema->hasOne('phone2', 'phones', 'property2');
        });

        $api->resource('phones', $phonesAdapter, function (Type $schema) {
            $schema->attribute('number');
        });

        $response = $api->handle($request);

        $this->assertArraySubset(
            [
                'relationships' => [
                    'phone2' => [
                        'data' => ['type' => 'phones', 'id' => '2']
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['data']
        );

        $this->assertEquals(
            [
                [
                    'type' => 'phones',
                    'id' => '2',
                    'attributes' => ['number' => '8882'],
                    'links' => [
                        'self' => 'http://example.com/phones/2'
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['included']
        );

        $response = $api->handle(
            $request->withQueryParams(['include' => 'phone'])
        );

        $this->assertArraySubset(
            [
                'relationships' => [
                    'phone' => [
                        'data' => ['type' => 'phones', 'id' => '1']
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['data']
        );

        $this->assertEquals(
            [
                [
                    'type' => 'phones',
                    'id' => '1',
                    'attributes' => ['number' => '8881'],
                    'links' => [
                        'self' => 'http://example.com/phones/1'
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['included']
        );
    }

    public function testHasManyRelationshipNotIncludableByDefault()
    {
        $api = new JsonApi('http://example.com');

        $api->resource('users', new MockAdapter(), function (Type $schema) {
            $schema->hasMany('groups');
        });

        $request = $this->buildRequest('GET', '/users/1')
            ->withQueryParams(['include' => 'groups']);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid include [groups]');

        $api->handle($request);
    }

    public function testHasManyRelationshipNotIncludedByDefault()
    {
        $usersAdapter = new MockAdapter([
            '1' => (object) [
                'id' => '1',
                'groups' => [
                    (object) ['id' => '1'],
                    (object) ['id' => '2'],
                ]
            ]
        ]);

        $api = new JsonApi('http://example.com');

        $api->resource('users', $usersAdapter, function (Type $schema) {
            $schema->hasMany('groups');
        });

        $api->resource('groups', new MockAdapter());

        $request = $this->buildRequest('GET', '/users/1');

        $response = $api->handle($request);

        $body = json_decode($response->getBody(), true);

        $this->assertArrayNotHasKey('data', $body['data']['relationships']);
        $this->assertArrayNotHasKey('included', $body);
    }

    public function testHasManyRelationshipInclusion()
    {
        $groupsAdapter = new MockAdapter([
            '1' => $group1 = (object) ['id' => '1', 'name' => 'Admin'],
            '2' => $group2 = (object) ['id' => '2', 'name' => 'Mod'],
            '3' => $group3 = (object) ['id' => '3', 'name' => 'Member'],
            '4' => $group4 = (object) ['id' => '4', 'name' => 'Guest']
        ]);

        $usersAdapter = new MockAdapter([
            '1' => $user = (object) [
                'id' => '1',
                'property1' => [$group1, $group2],
                'property2' => [$group3, $group4],
            ]
        ]);

        $api = new JsonApi('http://example.com');

        $relationships = [];

        $api->resource('users', $usersAdapter, function (Type $schema) use (&$relationships) {
            $relationships[] = $schema->hasMany('groups1', 'groups', 'property1')
                ->includable();

            $relationships[] = $schema->hasMany('groups2', 'groups', 'property2')
                ->includable();
        });

        $api->resource('groups', $groupsAdapter, function (Type $schema) {
            $schema->attribute('name');
        });

        $request = $this->buildRequest('GET', '/users/1')
            ->withQueryParams(['include' => 'groups1']);

        $response = $api->handle($request);

        $this->assertEquals([[$relationships[0]]], $user->load);

        $this->assertArraySubset(
            [
                'relationships' => [
                    'groups1' => [
                        'data' => [
                            ['type' => 'groups', 'id' => '1'],
                            ['type' => 'groups', 'id' => '2']
                        ]
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['data']
        );

        $this->assertEquals(
            [
                [
                    'type' => 'groups',
                    'id' => '1',
                    'attributes' => ['name' => 'Admin'],
                    'links' => [
                        'self' => 'http://example.com/groups/1'
                    ]
                ],
                [
                    'type' => 'groups',
                    'id' => '2',
                    'attributes' => ['name' => 'Mod'],
                    'links' => [
                        'self' => 'http://example.com/groups/2'
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['included']
        );

        $user->load = [];

        $response = $api->handle(
            $request->withQueryParams(['include' => 'groups2'])
        );

        $this->assertEquals([[$relationships[1]]], $user->load);

        $this->assertArraySubset(
            [
                'relationships' => [
                    'groups2' => [
                        'data' => [
                            ['type' => 'groups', 'id' => '3'],
                            ['type' => 'groups', 'id' => '4'],
                        ]
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['data']
        );

        $this->assertEquals(
            [
                [
                    'type' => 'groups',
                    'id' => '3',
                    'attributes' => ['name' => 'Member'],
                    'links' => [
                        'self' => 'http://example.com/groups/3'
                    ]
                ],
                [
                    'type' => 'groups',
                    'id' => '4',
                    'attributes' => ['name' => 'Guest'],
                    'links' => [
                        'self' => 'http://example.com/groups/4'
                    ]
                ],
            ],
            json_decode($response->getBody(), true)['included']
        );
    }

    public function testNestedRelationshipInclusion()
    {
        $groupsAdapter = new MockAdapter([
            '1' => $group1 = (object) ['id' => '1', 'name' => 'Admin'],
            '2' => $group2 = (object) ['id' => '2', 'name' => 'Mod']
        ]);

        $usersAdapter = new MockAdapter([
            '1' => $user = (object) ['id' => '1', 'groups' => [$group1, $group2]]
        ]);

        $postsAdapter = new MockAdapter([
            '1' => $post = (object) ['id' => '1', 'user' => $user]
        ]);

        $api = new JsonApi('http://example.com');

        $relationships = [];

        $api->resource('posts', $postsAdapter, function (Type $schema) use (&$relationships) {
            $relationships[] = $schema->hasOne('user');
        });

        $api->resource('users', $usersAdapter, function (Type $schema) use (&$relationships) {
            $relationships[] = $schema->hasMany('groups')->includable();
        });

        $api->resource('groups', $groupsAdapter, function (Type $schema) {
            $schema->attribute('name');
        });

        $request = $this->buildRequest('GET', '/posts/1')
            ->withQueryParams(['include' => 'user,user.groups']);

        $response = $api->handle($request);

        $this->assertEquals([$relationships[0]], $post->load[0]);
        $this->assertEquals($relationships, $post->load[1]);

        $this->assertArraySubset(
            [
                'relationships' => [
                    'user' => [
                        'data' => ['type' => 'users', 'id' => '1']
                    ]
                ]
            ],
            json_decode($response->getBody(), true)['data']
        );

        $included = json_decode($response->getBody(), true)['included'];

        // $this->assertContains(
        //     [
        //         'type' => 'users',
        //         'id' => '1',
        //         'relationships' => [
        //             'groups' => [
        //                 'data' => [
        //                     ['type' => 'groups', 'id' => '1'],
        //                     ['type' => 'groups', 'id' => '2']
        //                 ]
        //             ]
        //         ],
        //         'links' => [
        //             'self' => 'http://example.com/users/1'
        //         ]
        //     ],
        //     $included
        // );

        $this->assertContains(
            [
                'type' => 'groups',
                'id' => '1',
                'attributes' => ['name' => 'Admin'],
                'links' => [
                    'self' => 'http://example.com/groups/1'
                ]
            ],
            $included
        );

        $this->assertContains(
            [
                'type' => 'groups',
                'id' => '2',
                'attributes' => ['name' => 'Mod'],
                'links' => [
                    'self' => 'http://example.com/groups/2'
                ]
            ],
            $included
        );
    }
}
