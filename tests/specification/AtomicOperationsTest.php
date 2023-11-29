<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Extension\Atomic;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/ext/atomic/
 */
class AtomicOperationsTest extends AbstractTestCase
{
    private const MEDIA_TYPE = JsonApi::MEDIA_TYPE . '; ext=' . Atomic::URI;

    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->extension(new Atomic());

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'name' => 'Toby']],
                endpoints: [Create::make(), Update::make(), Delete::make()],
                fields: [Attribute::make('name')->writable()],
            ),
        );
    }

    public function test_atomic_operations()
    {
        $response = $this->api->handle(
            $this->buildRequest('POST', '/operations')
                ->withHeader('Accept', static::MEDIA_TYPE)
                ->withHeader('Content-Type', static::MEDIA_TYPE)
                ->withParsedBody([
                    'atomic:operations' => [
                        [
                            'op' => 'add',
                            'data' => [
                                'type' => 'users',
                                'lid' => 'franz',
                                'attributes' => ['name' => 'Franz'],
                            ],
                        ],
                        [
                            'op' => 'update',
                            'data' => [
                                'type' => 'users',
                                'lid' => 'franz',
                                'attributes' => ['name' => 'Franz2'],
                            ],
                        ],
                        [
                            'op' => 'remove',
                            'ref' => [
                                'type' => 'users',
                                'lid' => 'franz',
                            ],
                        ],
                    ],
                ]),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'atomic:results' => [
                    [
                        'data' => [
                            'type' => 'users',
                            'id' => 'created',
                            'attributes' => [
                                'name' => 'Franz',
                            ],
                        ],
                    ],
                    [
                        'data' => [
                            'type' => 'users',
                            'id' => 'created',
                            'attributes' => [
                                'name' => 'Franz2',
                            ],
                        ],
                    ],
                    null,
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_atomic_operations_error_prefix()
    {
        try {
            $this->api->handle(
                $this->buildRequest('POST', '/operations')
                    ->withHeader('Accept', static::MEDIA_TYPE)
                    ->withHeader('Content-Type', static::MEDIA_TYPE)
                    ->withParsedBody([
                        'atomic:operations' => [
                            [
                                'op' => 'update',
                                'ref' => ['type' => 'users', 'id' => '1'],
                                'data' => [],
                            ],
                        ],
                    ]),
            );

            $this->fail('BadRequestException was not thrown');
        } catch (BadRequestException $e) {
            $this->assertStringStartsWith(
                '/atomic:operations/0/data',
                $e->getJsonApiErrors()[0]['source']['pointer'],
            );
        }
    }
}
