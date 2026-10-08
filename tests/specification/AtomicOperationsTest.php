<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Extension\Atomic\Atomic;
use Tobyz\JsonApiServer\Extension\Relfield\Relfield;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/ext/atomic/
 */
class AtomicOperationsTest extends AbstractTestCase
{
    private const MEDIA_TYPE = JsonApi::MEDIA_TYPE . '; ext="' . Atomic::URI . '"';

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
            $this
                ->buildRequest('POST', '/operations')
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

    public function test_extensions_applied_to_operations_are_included_in_media_type()
    {
        $this->api->extension(new Relfield());

        $mediaType = JsonApi::MEDIA_TYPE . '; ext="' . Atomic::URI . ' ' . Relfield::URI . '"';

        $response = $this->api->handle(
            $this
                ->buildRequest('POST', '/operations')
                ->withHeader('Accept', $mediaType)
                ->withHeader('Content-Type', $mediaType)
                ->withParsedBody([
                    'atomic:operations' => [
                        [
                            'op' => 'update',
                            'params' => ['relfield:fields' => ['users' => '-name']],
                            'data' => [
                                'type' => 'users',
                                'id' => '1',
                                'attributes' => ['name' => 'Franz'],
                            ],
                        ],
                    ],
                ]),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertArrayNotHasKey('attributes', $document['atomic:results'][0]['data']);
        $this->assertSame(
            JsonApi::MEDIA_TYPE . '; ext="' . Relfield::URI . ' ' . Atomic::URI . '"',
            $response->getHeaderLine('Content-Type'),
        );
    }

    public function test_operation_members_are_not_forwarded_in_request_body()
    {
        $body = null;

        $this->api = new JsonApi();
        $this->api->extension(new Atomic());
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'name' => 'Toby']],
                endpoints: [
                    Update::make()->saved(function ($model, $context) use (&$body) {
                        $body = $context->body();
                    }),
                ],
                fields: [Attribute::make('name')->writable()],
            ),
        );

        $this->api->handle(
            $this
                ->buildRequest('POST', '/operations')
                ->withHeader('Accept', static::MEDIA_TYPE)
                ->withHeader('Content-Type', static::MEDIA_TYPE)
                ->withParsedBody([
                    'atomic:operations' => [
                        [
                            'op' => 'update',
                            'params' => ['include' => ''],
                            'data' => [
                                'type' => 'users',
                                'id' => '1',
                                'attributes' => ['name' => 'Franz'],
                            ],
                        ],
                    ],
                ]),
        );

        $this->assertSame(['data'], array_keys($body));
    }

    public function test_atomic_operations_error_prefix()
    {
        try {
            $this->api->handle(
                $this
                    ->buildRequest('POST', '/operations')
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
                $e->getJsonApiError()['source']['pointer'],
            );
        }
    }

    public function test_atomic_operations_schema()
    {
        $definition = (new OpenApiGenerator())->generate($this->api);

        $this->assertArraySubset(
            [
                'paths' => [
                    '/operations' => [
                        'post' => [
                            'requestBody' => [
                                'required' => true,
                                'content' => [
                                    static::MEDIA_TYPE => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/jsonApiAtomicOperationsDocument',
                                        ],
                                    ],
                                ],
                            ],
                            'responses' => [
                                '200' => [
                                    'content' => [
                                        static::MEDIA_TYPE => [
                                            'schema' => [
                                                '$ref' => '#/components/schemas/jsonApiAtomicResultsDocument',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'components' => [
                    'schemas' => [
                        'jsonApiAtomicOperationsDocument' => [
                            'required' => ['atomic:operations'],
                            'properties' => [
                                'atomic:operations' => [
                                    'items' => [
                                        '$ref' => '#/components/schemas/jsonApiAtomicOperation',
                                    ],
                                ],
                            ],
                        ],
                        'jsonApiAtomicOperation' => [
                            'required' => ['op'],
                            'properties' => [
                                'op' => [
                                    'enum' => ['add', 'update', 'remove'],
                                ],
                                'data' => [
                                    '$ref' => '#/components/schemas/jsonApiAtomicOperationData',
                                ],
                                'ref' => [
                                    '$ref' => '#/components/schemas/jsonApiAtomicRef',
                                ],
                            ],
                        ],
                        'jsonApiAtomicOperationData' => [
                            'anyOf' => [
                                ['$ref' => '#/components/schemas/jsonApiAtomicResourceObject'],
                                [
                                    '$ref' => '#/components/schemas/jsonApiAtomicRelationshipData',
                                ],
                            ],
                        ],
                        'jsonApiAtomicRelationshipData' => [
                            'oneOf' => [
                                ['$ref' => '#/components/schemas/jsonApiAtomicResourceIdentifier'],
                                ['type' => 'array'],
                                ['type' => 'null'],
                            ],
                        ],
                        'jsonApiAtomicResourceIdentifier' => [
                            'required' => ['type'],
                            'properties' => [
                                'lid' => ['type' => 'string'],
                            ],
                        ],
                        'jsonApiAtomicResourceObject' => [
                            'required' => ['type'],
                            'properties' => [
                                'lid' => ['type' => 'string'],
                                'relationships' => ['type' => 'object'],
                            ],
                        ],
                        'jsonApiAtomicRef' => [
                            'properties' => [
                                'relationship' => [
                                    'oneOf' => [['type' => 'string'], ['type' => 'object']],
                                ],
                            ],
                        ],
                        'jsonApiAtomicResultsDocument' => [
                            'required' => ['atomic:results'],
                            'properties' => [
                                'atomic:results' => [
                                    'items' => [
                                        'oneOf' => [
                                            ['type' => 'null'],
                                            [
                                                '$ref' => '#/components/schemas/jsonApiAtomicResultDocument',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'jsonApiAtomicResultDocument' => [
                            'properties' => [
                                'data' => [
                                    'anyOf' => [
                                        ['type' => 'object'],
                                        ['type' => 'array'],
                                        ['type' => 'null'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $definition,
        );
    }
}
