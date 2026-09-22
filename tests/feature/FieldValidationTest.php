<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Type\Arr;
use Tobyz\JsonApiServer\Schema\Type\Integer;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class FieldValidationTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    #[DataProvider('writeMethods')]
    public function test_typed_attribute_can_deserialize_to_a_model_on_create_and_update(string $method): void
    {
        $product = (object) ['id' => 2];
        $this->api->resource(
            $resource = new MockResource(
                'users',
                models: $method === 'PATCH' ? [(object) ['id' => '1', 'product' => null]] : [],
                endpoints: [Create::make(), Update::make()],
                fields: [
                    Attribute::make('product')
                        ->writable()
                        ->type(Integer::make())
                        ->deserialize(function (int $id) use ($product) {
                            $this->assertSame(2, $id);
                            return $product;
                        })
                        ->validate(function ($value) use ($product) {
                            $this->assertSame($product, $value);
                        })
                        ->serialize(fn(object $value) => $value->id),
                ],
            ),
        );

        $data = ['type' => 'users', 'attributes' => ['product' => 2]];
        if ($method === 'PATCH') {
            $data['id'] = '1';
        }

        $response = $this->api->handle($this->buildRequest(
            $method,
            $method === 'POST' ? '/users' : '/users/1',
        )->withParsedBody([
            'data' => $data,
        ]));
        $this->assertSame($method === 'POST' ? 201 : 200, $response->getStatusCode());
        $this->assertSame($product, $resource->models[0]->product);
    }

    public static function writeMethods(): iterable
    {
        yield 'create' => ['POST'];
        yield 'update' => ['PATCH'];
    }

    public function test_schema_errors_are_collected_before_custom_deserialization(): void
    {
        $this->api->resource(new MockResource('users', endpoints: [Create::make()], fields: array_map(
            fn($name) => Attribute::make($name)
                ->writable()
                ->type(Integer::make()->minimum(1))
                ->deserialize(fn() => $this->fail('Invalid input reached the deserializer.')),
            ['product', 'quantity'],
        )));

        try {
            $this->api->handle($this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['product' => 0, 'quantity' => 0]],
            ]));
            $this->fail('Expected schema validation to fail.');
        } catch (JsonApiErrorsException $e) {
            $this->assertSame(
                ['/data/attributes/product', '/data/attributes/quantity'],
                array_map(fn($error) => $error->getJsonApiError()['source']['pointer'], $e->errors),
            );
        }
    }

    public function test_null_attribute_default_requires_nullable_and_runs_custom_validation(): void
    {
        $field = Attribute::make('product')
            ->writable()
            ->type(Integer::make())
            ->default(null)
            ->deserialize(fn() => $this->fail('Null reached the deserializer.'));
        $this->api->resource(new MockResource('users', endpoints: [Create::make()], fields: [$field]));
        $request = $this->buildRequest('POST', '/users')->withParsedBody([
            'data' => ['type' => 'users'],
        ]);

        try {
            $this->api->handle($request);
            $this->fail('Expected a non-nullable default to fail.');
        } catch (JsonApiErrorsException $e) {
            $this->assertSame('/data/attributes/product', $e->errors[0]->getJsonApiError()['source']['pointer']);
        }

        $field->nullable();
        $this->assertSame(201, $this->api->handle($request)->getStatusCode());

        $field->validate(function ($value, $fail) {
            $this->assertNull($value);
            $fail('A product must be selected.');
        });
        $this->expectException(JsonApiErrorsException::class);
        $this->api->handle($request);
    }

    public function test_validate_on_create()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('name')
                        ->writable()
                        ->validate(fn($value, $fail) => $fail()),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle($this->buildRequest('POST', '/users')->withParsedBody([
            'data' => ['type' => 'users', 'attributes' => ['name' => 'Toby']],
        ]));
    }

    public function test_validate_on_update()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Update::make()],
                fields: [
                    Attribute::make('name')
                        ->writable()
                        ->validate(fn($value, $fail) => $fail()),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle($this->buildRequest('PATCH', '/users/1')->withParsedBody([
            'data' => ['type' => 'users', 'id' => '1', 'attributes' => ['name' => 'Toby']],
        ]));
    }

    #[DataProvider('invalidInputs')]
    public function test_validate_is_not_run_for_invalid_input(Type $type, mixed $value): void
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('value')
                        ->type($type)
                        ->writable()
                        ->validate(fn() => $this->fail('Invalid input reached the validator.')),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle($this->buildRequest('POST', '/users')->withParsedBody([
            'data' => ['type' => 'users', 'attributes' => ['value' => $value]],
        ]));
    }

    public static function invalidInputs(): iterable
    {
        yield 'invalid type' => [Arr::make(), 1];
        yield 'non-nullable null' => [Str::make(), null];
    }
}
