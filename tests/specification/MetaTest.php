<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Met;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Meta;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/#document-meta
 */
class MetaTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_resource_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'foo' => 'bar']],
                endpoints: [Show::make()],
                meta: [Meta::make('foo')],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['meta' => ['foo' => 'bar']]],
            $response->getBody(),
        );
    }

    public function test_to_one_relationship_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()],
                fields: [ToOne::make('pet')->meta([Meta::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $this->api->resource(new MockResource('pets'));

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['relationships' => ['pet' => ['meta' => ['foo' => 'bar']]]]],
            $response->getBody(),
        );
    }

    public function test_to_many_relationship_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()],
                fields: [ToMany::make('pets')->meta([Meta::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $this->api->resource(new MockResource('pets'));

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['relationships' => ['pets' => ['meta' => ['foo' => 'bar']]]]],
            $response->getBody(),
        );
    }

    public function test_to_one_linkage_meta()
    {
        $role = (object) ['id' => '1', 'name' => 'admin'];

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'role' => $role]],
                endpoints: [Show::make()],
                fields: [
                    ToOne::make('role')
                        ->get(fn($user) => $user->role)
                        ->linkageMeta([Meta::make('active')->get(fn() => true)]),
                ],
            ),
        );

        $this->api->resource(new MockResource('roles', models: [$role]));

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'relationships' => [
                        'role' => [
                            'data' => [
                                'type' => 'roles',
                                'id' => '1',
                                'meta' => ['active' => true],
                            ],
                        ],
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_to_many_linkage_meta()
    {
        $role1 = (object) ['id' => '1', 'name' => 'admin', 'assignedAt' => '2024-01-01'];
        $role2 = (object) ['id' => '2', 'name' => 'editor', 'assignedAt' => '2024-01-02'];

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'roles' => [$role1, $role2]]],
                endpoints: [Show::make()],
                fields: [
                    ToMany::make('roles')
                        ->get(fn($user) => $user->roles)
                        ->withLinkage()
                        ->linkageMeta([
                            Meta::make('assignedAt')->get(fn($role) => $role->assignedAt),
                        ]),
                ],
            ),
        );

        $this->api->resource(new MockResource('roles', models: [$role1, $role2]));

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'relationships' => [
                        'roles' => [
                            'data' => [
                                [
                                    'type' => 'roles',
                                    'id' => '1',
                                    'meta' => ['assignedAt' => '2024-01-01'],
                                ],
                                [
                                    'type' => 'roles',
                                    'id' => '2',
                                    'meta' => ['assignedAt' => '2024-01-02'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_show_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'foo' => 'bar']],
                endpoints: [Show::make()->meta([Meta::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }

    public function test_update_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'foo' => 'bar']],
                endpoints: [Update::make()->meta([Meta::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')->withParsedBody([
                'data' => ['type' => 'users', 'id' => '1'],
            ]),
        );

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }

    public function test_delete_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'foo' => 'bar']],
                endpoints: [Delete::make()->meta([Meta::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle($this->buildRequest('DELETE', '/users/1'));

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }

    public function test_index_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Index::make()->meta([Meta::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users'));

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }

    public function test_create_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()->meta([Meta::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users'],
            ]),
        );

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }
}
