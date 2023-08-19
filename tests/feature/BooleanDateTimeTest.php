<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\BooleanDateTime;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class BooleanDateTimeTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_validates_boolean()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    BooleanDateTime::make('isDeleted')
                        ->property('deletedAt')
                        ->writable(),
                ],
            ),
        );

        $this->expectException(UnprocessableEntityException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['isDeleted' => 'hello']],
            ]),
        );
    }

    public function test_sets_value_as_date_time()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [($user = (object) ['id' => '1'])],
                endpoints: [Update::make()],
                fields: [
                    BooleanDateTime::make('isDeleted')
                        ->property('deletedAt')
                        ->writable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')->withParsedBody([
                'data' => ['type' => 'users', 'id' => '1', 'attributes' => ['isDeleted' => true]],
            ]),
        );

        $this->assertInstanceOf(\DateTime::class, $user->deletedAt);

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['isDeleted' => true]]],
            $response->getBody(),
            true,
        );
    }

    public function test_sets_value_as_null()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [($user = (object) ['id' => '1'])],
                endpoints: [Update::make()],
                fields: [
                    BooleanDateTime::make('isDeleted')
                        ->property('deletedAt')
                        ->writable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')->withParsedBody([
                'data' => ['type' => 'users', 'id' => '1', 'attributes' => ['isDeleted' => false]],
            ]),
        );

        $this->assertNull($user->deletedAt);

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['isDeleted' => false]]],
            $response->getBody(),
            true,
        );
    }
}
