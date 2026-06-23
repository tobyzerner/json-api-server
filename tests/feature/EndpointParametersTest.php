<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class EndpointParametersTest extends AbstractTestCase
{
    public function test_endpoint_parameter()
    {
        $parameters = $this->parameterValues(
            [Parameter::make('testParameter')],
            ['testParameter' => 'value'],
        );

        $this->assertSame(['testParameter' => 'value'], $parameters);
    }

    public function test_boolean_query_parameter_is_normalized(): void
    {
        $parameters = $this->parameterValues(
            [Parameter::make('active')->type(Type\Boolean::make())],
            ['active' => '1'],
        );

        $this->assertTrue($parameters['active']);
    }

    public function test_integer_and_number_query_parameters_are_normalized(): void
    {
        $parameters = $this->parameterValues(
            [
                Parameter::make('count')->type(Type\Integer::make()),
                Parameter::make('score')->type(Type\Number::make()),
            ],
            [
                'count' => '2',
                'score' => '3.5',
            ],
        );

        $this->assertSame(2, $parameters['count']);
        $this->assertSame(3.5, $parameters['score']);
    }

    public function test_scalar_query_parameter_is_normalized_to_array(): void
    {
        $parameters = $this->parameterValues(
            [Parameter::make('ids')->type(Type\Arr::make()->items(Type\Integer::make()))],
            ['ids' => '1'],
        );

        $this->assertSame([1], $parameters['ids']);
    }

    public function test_comma_separated_array_query_parameter_is_normalized(): void
    {
        $parameters = $this->parameterValues(
            [
                Parameter::make('ids')->type(
                    Type\Arr::make()
                        ->items(Type\Integer::make())
                        ->commaSeparated(),
                ),
            ],
            ['ids' => '1,2,3'],
        );

        $this->assertSame([1, 2, 3], $parameters['ids']);
    }

    public function test_nested_object_query_parameter_properties_are_normalized(): void
    {
        $parameters = $this->parameterValues(
            [
                Parameter::make('range')->type(
                    Type\Obj::make()
                        ->property('min', Type\Integer::make())
                        ->property('max', Type\Integer::make()),
                ),
            ],
            [
                'range' => ['min' => '1', 'max' => '2'],
            ],
        );

        $this->assertSame(['min' => 1, 'max' => 2], $parameters['range']);
    }

    public function test_custom_query_parameter_deserializer_receives_normalized_typed_value(): void
    {
        $seen = null;

        $parameters = $this->parameterValues(
            [
                Parameter::make('count')
                    ->type(Type\Integer::make())
                    ->deserialize(function ($value) use (&$seen) {
                        $seen = $value;

                        return $value + 1;
                    }),
            ],
            ['count' => '2'],
        );

        $this->assertSame(2, $seen);
        $this->assertSame(3, $parameters['count']);
    }

    public function test_invalid_array_query_parameter_item_reports_nested_source(): void
    {
        $this->assertInvalidParameterSource(
            [Parameter::make('ids')->type(Type\Arr::make()->items(Type\Integer::make()))],
            ['ids' => 'nope'],
            'ids[0]',
        );
    }

    public function test_invalid_object_query_parameter_property_reports_nested_source(): void
    {
        $this->assertInvalidParameterSource(
            [
                Parameter::make('range')->type(
                    Type\Obj::make()->property('min', Type\Integer::make()),
                ),
            ],
            ['range' => ['min' => 'nope']],
            'range[min]',
        );
    }

    /**
     * @param Parameter[] $parameters
     */
    private function parameterValues(array $parameters, array $queryParams): array
    {
        $api = $this->apiWithParameters($parameters);
        $response = $api->handle(
            $this->buildRequest('GET', '/users/1')->withQueryParams($queryParams),
        );
        $document = json_decode($response->getBody(), true);

        return $document['data']['attributes']['parameters'];
    }

    /**
     * @param Parameter[] $parameters
     */
    private function assertInvalidParameterSource(
        array $parameters,
        array $queryParams,
        string $source,
    ): void {
        try {
            $this->parameterValues($parameters, $queryParams);
        } catch (JsonApiErrorsException $e) {
            $error = $e->errors[0]->getJsonApiError();

            $this->assertSame($source, $error['source']['parameter'] ?? null);

            return;
        }

        $this->fail('Expected a JSON:API errors exception.');
    }

    /**
     * @param Parameter[] $parameters
     */
    private function apiWithParameters(array $parameters): JsonApi
    {
        $api = new JsonApi();

        $api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()->parameters($parameters)],
                fields: [
                    Attribute::make('parameters')->get(function ($model, $context) use (
                        $parameters,
                    ) {
                        $values = [];

                        foreach ($parameters as $parameter) {
                            $value = $context->parameter($parameter->name);

                            if ($value !== null) {
                                $values[$parameter->name] = $value;
                            }
                        }

                        return $values;
                    }),
                ],
            ),
        );

        return $api;
    }
}
