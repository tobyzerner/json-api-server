<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Type\Date;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class DateTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_serializes_value_to_date()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'dob' => new \DateTime('2023-01-01')]],
                endpoints: [Show::make()],
                fields: [Attribute::make('dob')->type(Date::make())],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['dob' => '2023-01-01']]],
            $response->getBody(),
        );
    }

    public function test_deserializes_input_to_datetime()
    {
        $this->api->resource(
            $resource = new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('dob')
                        ->type(Date::make())
                        ->writable(),
                ],
            ),
        );

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['dob' => '2023-01-01']],
            ]),
        );

        $this->assertInstanceOf(\DateTime::class, $resource->models[0]->dob);
    }

    public function test_validates_date()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('dob')
                        ->type(Date::make())
                        ->writable(),
                ],
            ),
        );

        $this->expectException(UnprocessableEntityException::class);

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['dob' => 'hello']],
            ]),
        );
    }
}
