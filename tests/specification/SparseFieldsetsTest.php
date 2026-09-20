<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/#fetching-sparse-fieldsets
 */
class SparseFieldsetsTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'users',
                models: [($user1 = (object) ['id' => '1', 'name' => 'Toby', 'color' => 'yellow'])],
                fields: [Attribute::make('name'), Attribute::make('color')],
            ),
        );

        $this->api->resource(
            new class (
                'articles',
                models: [
                    '1' => (object) [
                        'id' => '1',
                        'title' => 'foo',
                        'body' => 'bar',
                        'exclude' => 'baz',
                        'author' => $user1,
                    ],
                ],
                endpoints: [Show::make()],
                fields: [
                    Attribute::make('title'),
                    Attribute::make('body'),
                    Attribute::make('exclude')->sparse(),
                    ToOne::make('author')
                        ->type('users')
                        ->includable(),
                ],
            ) extends MockResource {
                public function find(string $id, Context $context): ?object
                {
                    $model = parent::find($id, $context);

                    foreach (['title', 'body', 'exclude'] as $field) {
                        if (!$context->fieldRequested('articles', $field)) {
                            unset($model->$field);
                        }
                    }

                    return $model;
                }
            },
        );
    }

    public function test_sparse_fieldsets()
    {
        $response = $this->api->handle(
            $this->buildRequest(
                'GET',
                '/articles/1?include=author&fields[articles]=title,exclude,author&fields[users]=name',
            ),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'articles',
                    'id' => '1',
                    'attributes' => ['title' => 'foo', 'exclude' => 'baz'],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'users', 'id' => '1'],
                        ],
                    ],
                ],
                'included' => [
                    [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => ['name' => 'Toby'],
                    ],
                ],
            ],
            $body = $response->getBody(),
        );

        $document = json_decode($body, true);

        $this->assertArrayNotHasKey('body', $document['data']['attributes']);
        $this->assertArrayNotHasKey('color', $document['included'][0]['attributes']);
    }

    public function test_sparse_by_default()
    {
        $response = $this->api->handle($this->buildRequest('GET', '/articles/1'));

        $document = json_decode($response->getBody(), true);

        $this->assertSame(['title' => 'foo', 'body' => 'bar'], $document['data']['attributes']);
    }

    public function test_empty_fieldset(): void
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1?fields[articles]='),
        );

        $document = json_decode($response->getBody(), true);
        $this->assertSame('1', $document['data']['id']);
        $this->assertArrayNotHasKey('attributes', $document['data']);
        $this->assertArrayNotHasKey('relationships', $document['data']);
    }

    public function test_invalid_fieldset(): void
    {
        try {
            $this->api->handle($this->buildRequest('GET', '/articles/1?fields[articles][invalid]=title'));
            $this->fail('Expected invalid fields to be rejected.');
        } catch (JsonApiErrorsException $e) {
            $this->assertSame('fields[articles]', $e->errors[0]->getJsonApiError()['source']['parameter']);
        }
    }
}
