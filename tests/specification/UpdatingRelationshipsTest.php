<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/1.1/#crud-updating-relationships
 */
class UpdatingRelationshipsTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_replace_to_one_relationship()
    {
        $this->api->resource(new MockResource('pets', models: [(object) ['id' => '1']]));

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Update::make()],
                fields: [ToOne::make('pet')->writable()],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('PATCH', '/users/1/relationships/pet')->withParsedBody([
                'data' => ['type' => 'pets', 'id' => '1'],
            ]),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['type' => 'pets', 'id' => '1'], $document['data'] ?? null);
    }

    public function test_replace_to_many_relationship()
    {
        $this->api->resource(
            new MockResource('pets', models: [(object) ['id' => '1'], (object) ['id' => '2']]),
        );

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Update::make()],
                fields: [ToMany::make('pets')->writable()->attachable()],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('PATCH', '/users/1/relationships/pets')->withParsedBody([
                'data' => [['type' => 'pets', 'id' => '1'], ['type' => 'pets', 'id' => '2']],
            ]),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            [['type' => 'pets', 'id' => '1'], ['type' => 'pets', 'id' => '2']],
            $document['data'] ?? null,
        );
    }

    public function test_attach_to_many_relationship()
    {
        $this->api->resource(
            new MockResource(
                'pets',
                models: [($pet = (object) ['id' => '1']), (object) ['id' => '2']],
            ),
        );

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'pets' => [$pet]]],
                endpoints: [Update::make()],
                fields: [ToMany::make('pets')->writable()->attachable()],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users/1/relationships/pets')->withParsedBody([
                'data' => [['type' => 'pets', 'id' => '2']],
            ]),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            [['type' => 'pets', 'id' => '1'], ['type' => 'pets', 'id' => '2']],
            $document['data'] ?? null,
        );
    }

    public function test_detach_to_many_relationship()
    {
        $this->api->resource(
            new MockResource(
                'pets',
                models: [($pet1 = (object) ['id' => '1']), ($pet2 = (object) ['id' => '2'])],
            ),
        );

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'pets' => [$pet1, $pet2]]],
                endpoints: [Update::make()],
                fields: [ToMany::make('pets')->writable()->attachable()],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('DELETE', '/users/1/relationships/pets')->withParsedBody([
                'data' => [['type' => 'pets', 'id' => '2']],
            ]),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([['type' => 'pets', 'id' => '1']], $document['data'] ?? null);
    }

    public function test_attach_to_many_relationship_validation_failure()
    {
        $this->api->resource(
            new MockResource(
                'pets',
                models: [($pet1 = (object) ['id' => '1']), ($pet2 = (object) ['id' => '2'])],
            ),
        );

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'pets' => [$pet1]]],
                endpoints: [Update::make()],
                fields: [
                    ToMany::make('pets')
                        ->writable()
                        ->attachable()
                        ->validateAttach(function ($fail, array $related) use ($pet2) {
                            foreach ($related as $index => $candidate) {
                                if ($candidate === $pet2) {
                                    $fail('cannot attach second pet', $index);
                                }
                            }
                        }),
                ],
            ),
        );

        try {
            $this->api->handle(
                $this->buildRequest('POST', '/users/1/relationships/pets')->withParsedBody([
                    'data' => [['type' => 'pets', 'id' => '2']],
                ]),
            );

            $this->fail('Expected UnprocessableEntityException to be thrown.');
        } catch (UnprocessableEntityException $e) {
            $this->assertEquals('cannot attach second pet', $e->errors[0]['detail'] ?? null);
            $this->assertEquals('/data/0', $e->errors[0]['source']['pointer'] ?? null);
        }
    }

    public function test_post_to_many_relationship_without_attachable_replaces_relationship()
    {
        $this->api->resource(
            new MockResource(
                'pets',
                models: [($pet1 = (object) ['id' => '1']), ($pet2 = (object) ['id' => '2'])],
            ),
        );

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'pets' => [$pet1]]],
                endpoints: [Update::make()],
                fields: [
                    ToMany::make('pets')->writable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users/1/relationships/pets')->withParsedBody([
                'data' => [['type' => 'pets', 'id' => '2']],
            ]),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            [['type' => 'pets', 'id' => '1'], ['type' => 'pets', 'id' => '2']],
            $document['data'] ?? null,
        );
    }
}
