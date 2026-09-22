<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class ParametersTest extends AbstractTestCase
{
    public function test_integer_parameter_can_deserialize_to_a_model(): void
    {
        $product = (object) ['id' => 2];
        $parameter = Parameter::make('product')
            ->type(Type\Integer::make())
            ->default(2)
            ->deserialize(function ($id) use ($product) {
                $this->assertSame(2, $id);
                return $product;
            })
            ->validate(function ($value) use ($product) {
                $this->assertSame($product, $value);
            });

        $this->assertSame(['product' => ['id' => 2]], $this->parameterValues([$parameter], ['product' => '2']));
        $this->assertSame(['product' => ['id' => 2]], $this->parameterValues([$parameter], []));
    }

    public function test_invalid_parameter_input_does_not_reach_custom_callbacks(): void
    {
        $parameter = Parameter::make('product')
            ->type(Type\Integer::make()->minimum(1))
            ->deserialize(fn() => $this->fail('Invalid input reached the deserializer.'))
            ->validate(fn() => $this->fail('Invalid input reached the validator.'));

        $this->assertInvalidParameterSource([$parameter], ['product' => '0'], 'product');
    }

    public function test_omitted_parameter_skips_custom_callbacks(): void
    {
        $parameter = Parameter::make('product')
            ->type(Type\Integer::make())
            ->deserialize(fn() => $this->fail('Omitted input reached the deserializer.'))
            ->validate(fn() => $this->fail('Omitted input reached the validator.'));

        $this->assertSame([], $this->parameterValues([$parameter], []));
    }

    public function test_explicit_null_does_not_use_the_parameter_default(): void
    {
        $parameter = Parameter::make('product')
            ->type(Type\Integer::make())
            ->default(2);

        $this->assertInvalidParameterSource([$parameter], ['product' => null], 'product');
    }

    public function test_null_parameter_default_requires_nullable_and_runs_custom_validation(): void
    {
        $parameter = Parameter::make('product')
            ->type(Type\Integer::make())
            ->default(null)
            ->deserialize(fn() => $this->fail('Null reached the deserializer.'));

        $this->assertInvalidParameterSource([$parameter], [], 'product');

        $parameter->nullable();
        $this->assertSame([], $this->parameterValues([$parameter], []));

        $parameter->validate(function ($value, $fail) {
            $this->assertNull($value);
            $fail('A product must be selected.');
        });
        $this->assertInvalidParameterSource([$parameter], [], 'product');
        $this->assertInvalidParameterSource([$parameter], ['product' => null], 'product');
    }

    public function test_required_nullable_parameter_must_be_present(): void
    {
        $parameter = Parameter::make('product')->required()->nullable();

        $this->assertInvalidParameterSource([$parameter], [], 'product');
        $this->assertSame([], $this->parameterValues([$parameter], ['product' => null]));
    }

    public function test_custom_validator_can_reject_a_deserialized_model(): void
    {
        $parameter = Parameter::make('product')
            ->type(Type\Integer::make())
            ->deserialize(fn(int $id) => (object) ['id' => $id])
            ->validate(function (object $product, $fail) {
                $this->assertSame(2, $product->id);
                $fail('Product is unavailable.');
            });

        $this->assertInvalidParameterSource([$parameter], ['product' => '2'], 'product');
    }

    public function test_deserialized_null_is_not_treated_as_an_omitted_parameter(): void
    {
        $parameter = Parameter::make('product')
            ->type(Type\Integer::make())
            ->deserialize(fn(int $id) => null);

        $this->assertInvalidParameterSource([$parameter], ['product' => '2'], 'product');
        $parameter->nullable()->validate(fn($value, $fail) => $fail('Product was not found.'));
        $this->assertInvalidParameterSource([$parameter], ['product' => '2'], 'product');
    }

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

    public function test_enum_query_parameter_is_normalized_to_case(): void
    {
        $parameters = $this->parameterValues(
            [
                Parameter::make('status')
                    ->type(Type\Str::make()->enum(ParameterStatus::cases()))
                    ->deserialize(function ($value) {
                        $this->assertSame(ParameterStatus::Active, $value);

                        return $value->value;
                    }),
            ],
            ['status' => 'active'],
        );

        $this->assertSame('active', $parameters['status']);
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

    public function test_api_parameters_reach_lookup_and_included_resources(): void
    {
        $api = $this->api();
        $api->parameters([
            Parameter::make('locale')
                ->default('EN')
                ->deserialize(fn($value) => strtolower($value)),
        ]);

        $response = $api->handle($this->buildRequest('GET', '/users/1')->withQueryParams(['include' => 'pets']));
        $document = json_decode($response->getBody(), true);

        $this->assertSame('en', $document['data']['attributes']['lookupLocale']);
        $this->assertSame('en', $document['included'][0]['attributes']['locale']);
    }

    public function test_relationship_parameters_are_reused_within_each_request(): void
    {
        $assertParameterReused = function ($response, $model, Context $context) {
            $this->assertSame($model->lookupLocale, $context->parameter('locale'));
        };
        $api = $this->api(
            Show::make()
                ->showRelated(fn($endpoint) => $endpoint->response($assertParameterReused))
                ->showRelationship(fn($endpoint) => $endpoint->response($assertParameterReused)),
        );
        $api->parameters([
            Parameter::make('locale')
                ->default('EN')
                ->deserialize(fn($value) => (object) ['code' => strtolower($value)]),
            Parameter::make('optional')->deserialize(
                fn() => $this->fail('Omitted input reached the deserializer.'),
            ),
        ]);

        $previousLocale = null;
        foreach (['/users/1/pets', '/users/1/relationships/pets'] as $path) {
            $response = $api->handle($this->buildRequest('GET', $path));
            $document = json_decode($response->getBody(), true);
            $this->assertSame('2', $document['data'][0]['id']);
            $locale = $api->getResource('users')->models[0]->lookupLocale;
            $this->assertSame('en', $locale->code);
            $this->assertNotSame($previousLocale, $locale);
            $previousLocale = $locale;
        }
    }

    public function test_invalid_api_parameter_is_rejected_before_lookup(): void
    {
        $api = $this->api();
        $api->parameters([Parameter::make('locale')->type(Type\Str::make()->enum(['en']))]);

        try {
            $api->handle($this->buildRequest('GET', '/users/missing/pets')->withQueryParams(['locale' => 'fr']));
            $this->fail('Expected parameter validation to fail before model lookup.');
        } catch (JsonApiErrorsException $e) {
            $this->assertSame('locale', $e->errors[0]->getJsonApiError()['source']['parameter']);
        }
    }

    public function test_endpoint_overrides_are_merged_by_location_and_name_in_runtime_and_openapi(): void
    {
        $api = $this->api(Show::make()->parameters([
            Parameter::make('locale')->deserialize(fn() => $this->fail('Superseded definition ran.')),
            Parameter::make('locale')
                ->default('fr')
                ->type(Type\Str::make()->enum(['fr'])),
        ]));
        $api->parameters([
            Parameter::make('locale')
                ->required()
                ->deserialize(fn() => $this->fail('Overridden API definition ran.')),
            Parameter::make('locale')
                ->in('header')
                ->required()
                ->type(Type\Str::make()),
        ]);

        $response = $api->handle($this->buildRequest('GET', '/users/1')->withHeader('locale', 'de'));
        $document = json_decode($response->getBody(), true);
        $this->assertSame('fr', $document['data']['attributes']['locale']);
        $this->assertSame('de', $document['data']['attributes']['headerLocale']);

        $definition = (new OpenApiGenerator())->generate($api);
        $parameters = array_values(array_filter(
            $definition['paths']['/users/{id}']['get']['parameters'],
            fn($parameter) => $parameter['name'] === 'locale',
        ));
        $this->assertCount(2, $parameters);
        $this->assertSame('query', $parameters[0]['in']);
        $this->assertSame(['fr'], $parameters[0]['schema']['enum']);
        $this->assertArrayNotHasKey('required', $parameters[0]);
        $this->assertSame('header', $parameters[1]['in']);
        $this->assertTrue($parameters[1]['required']);

        $relationshipParameters = $definition['paths']['/users/{id}/relationships/pets']['get']['parameters'];
        $this->assertCount(2, array_filter($relationshipParameters, fn($parameter) => $parameter['name'] === 'locale'));
    }

    public function test_header_parameters_require_an_explicit_location(): void
    {
        $api = $this->api();
        $api->parameters([Parameter::make('locale')->in('header')]);

        $response = $api->handle($this->buildRequest('GET', '/users/1')->withHeader('locale', 'de'));
        $attributes = json_decode($response->getBody(), true)['data']['attributes'];

        $this->assertNull($attributes['locale']);
        $this->assertSame('de', $attributes['headerLocale']);
    }

    public function test_relationship_endpoint_overrides_are_available_before_lookup(): void
    {
        $parameters = [Parameter::make('locale')->default('fr')];
        $api = $this->api(
            Show::make()
                ->showRelated(fn($endpoint) => $endpoint->parameters($parameters))
                ->showRelationship(fn($endpoint) => $endpoint->parameters($parameters)),
        );
        $api->parameters([
            Parameter::make('locale')->deserialize(
                fn() => $this->fail('Overridden API definition ran.'),
            ),
        ]);

        foreach (['/users/1/pets', '/users/1/relationships/pets'] as $path) {
            $api->handle($this->buildRequest('GET', $path));
            $this->assertSame('fr', $api->getResource('users')->models[0]->lookupLocale);
        }
    }

    #[DataProvider('reservedQueryParameters')]
    public function test_api_rejects_endpoint_owned_query_parameters(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Query parameter '$name' is reserved for endpoint configuration.");

        (new JsonApi())->parameters([Parameter::make($name)]);
    }

    public static function reservedQueryParameters(): iterable
    {
        foreach (['page', 'filter', 'sort', 'include', 'fields'] as $family) {
            yield $family => [$family];
            yield $family . '[child]' => [$family . '[child]'];
        }
    }

    public function test_reserved_names_only_restrict_query_families(): void
    {
        $parameters = [
            Parameter::make('page')->in('header'),
            Parameter::make('pageSize'),
            Parameter::make('filterMode'),
        ];
        $api = (new JsonApi())->parameters($parameters);

        $this->assertSame($parameters, $api->getParameters());
    }

    public function test_custom_pagination_rejects_parameters_outside_the_page_family(): void
    {
        $api = new JsonApi();
        $api->resource(new MockResource(
            'users',
            endpoints: [Show::make()],
            fields: [ToMany::make('pets')->type('pets')],
        ));
        $api->resource(new MockResource(
            'pets',
            pagination: new class extends OffsetPagination {
                public function parameters(): array
                {
                    return [Parameter::make('limit')];
                }
            },
        ));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Pagination parameter 'limit' must be a page[...] query parameter.");

        (new OpenApiGenerator())->generate($api);
    }

    public function test_shared_headers_do_not_control_collection_filtering_or_sorting(): void
    {
        $api = (new JsonApi())->parameters([
            Parameter::make('filter')->in('header'),
            Parameter::make('sort')->in('header'),
        ]);
        $api->resource(new MockResource(
            'users',
            models: [(object) ['id' => '1']],
            endpoints: [Index::make()],
            fields: [
                Attribute::make('headerFilter')->get(
                    fn($model, Context $context) => $context->parameter('filter', 'header'),
                ),
                Attribute::make('headerSort')->get(
                    fn($model, Context $context) => $context->parameter('sort', 'header'),
                ),
                Attribute::make('filters')->get(
                    fn($model, Context $context) => $context->filters(),
                ),
                Attribute::make('sortRequested')->get(
                    fn($model, Context $context) => $context->sortRequested('name'),
                ),
            ],
        ));

        $response = $api->handle(
            $this->buildRequest('GET', '/users')
                ->withHeader('filter', 'shared context')
                ->withHeader('sort', 'name'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'headerFilter' => 'shared context',
            'headerSort' => 'name',
            'filters' => [],
            'sortRequested' => false,
        ], json_decode($response->getBody(), true)['data'][0]['attributes']);
    }

    /**
     * @param Parameter[] $parameters
     */
    private function parameterValues(array $parameters, array $queryParams): array
    {
        $api = new JsonApi();

        $api->resource(
            $resource = new class (
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()->parameters($parameters)],
                fields: [
                    Attribute::make('lookupParameters'),
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
            ) extends MockResource {
                public array $parameterDefinitions;

                public function find(string $id, Context $context): ?object
                {
                    $model = parent::find($id, $context);
                    $model->lookupParameters = [];

                    foreach ($this->parameterDefinitions as $parameter) {
                        if (($value = $context->parameter($parameter->name)) !== null) {
                            $model->lookupParameters[$parameter->name] = $value;
                        }
                    }

                    return $model;
                }
            },
        );

        $resource->parameterDefinitions = $parameters;

        $response = $api->handle(
            $this->buildRequest('GET', '/users/1')->withQueryParams($queryParams),
        );
        $document = json_decode($response->getBody(), true);

        $this->assertSame(
            $document['data']['attributes']['parameters'],
            $document['data']['attributes']['lookupParameters'],
        );

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

    private function api(?Show $show = null): JsonApi
    {
        $api = new JsonApi();
        $pets = [(object) ['id' => '2'], (object) ['id' => '3']];
        $api->resource(
            new MockResource(
                'pets',
                models: $pets,
                fields: [Attribute::make('locale')->get(fn($model, Context $context) => $context->parameter('locale'))],
                pagination: new OffsetPagination(),
            ),
        );
        $api->resource(new class(
            'users',
            models: [(object) ['id' => '1', 'pets' => $pets]],
            endpoints: [$show ?? Show::make()],
            fields: [
                Attribute::make('lookupLocale'),
                Attribute::make('locale')->get(fn($model, Context $context) => $context->parameter('locale')),
                Attribute::make('headerLocale')->get(
                    fn($model, Context $context) => $context->parameter('locale', 'header'),
                ),
                ToMany::make('pets')->type('pets')->includable(),
            ],
        ) extends MockResource {
            public function find(string $id, Context $context): ?object
            {
                $model = parent::find($id, $context);
                if ($model) {
                    $model->lookupLocale = $context->parameter('locale');
                }
                return $model;
            }
        });
        return $api;
    }
}

enum ParameterStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
