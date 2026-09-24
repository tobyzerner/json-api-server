<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\ShowRelated;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\Filter\UnknownFilterException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class FilteringTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'items',
                models: [
                    (object) ['id' => '1', 'active' => true, 'score' => 10],
                    (object) ['id' => '2', 'active' => false, 'score' => 20],
                    (object) ['id' => '3', 'active' => false, 'score' => 30],
                ],
                endpoints: [Index::make()],
                fields: [Attribute::make('active'), Attribute::make('score')],
                filters: [
                    CustomFilter::make('active', function ($query, bool $value): void {
                        $query->models = array_filter(
                            $query->models,
                            fn($model) => $model->active === $value,
                        );
                    })->type(Type\Boolean::make()),

                    CustomFilter::make('ids', function ($query, array $value): void {
                        $query->models = array_filter(
                            $query->models,
                            fn($model) => in_array((int) $model->id, $value, true),
                        );
                    })->type(
                        Type\Arr::make()
                            ->items(Type\Integer::make())
                            ->commaSeparated(),
                    ),

                    CustomFilter::make('created', function ($query, array $value): void {
                        $query->seen = $value;
                    })->type(Type\Arr::make()->items(Type\Date::make())),

                    CustomFilter::make('createdAt', function ($query, array $value): void {
                        $query->seen = $value;
                    })
                        ->type(Type\Date::make())
                        ->operators([
                            'eq',
                            'lt',
                            'gt',
                            'null' => Type\Boolean::make(),
                            'notnull' => Type\Boolean::make(),
                        ]),

                    CustomFilter::make('status', function (
                        $query,
                        FilteringTestStatus $value,
                    ): void {
                        $query->seen = $value;
                    })->type(Type\Str::make()->enum(FilteringTestStatus::cases())),

                    CustomFilter::make('range', function ($query, array $value): void {
                        $query->seen = $value;
                    })->type(
                        Type\Obj::make()
                            ->property('min', Type\Integer::make())
                            ->property('max', Type\Integer::make())
                            ->additionalProperties(Type\Number::make()),
                    ),

                    CustomFilter::make('rangeOperator', function ($query, array $value): void {
                        $query->seen = $value;
                    })
                        ->type(
                            Type\Obj::make()
                                ->property('min', Type\Integer::make())
                                ->property('max', Type\Integer::make()),
                        )
                        ->operators(['eq', 'gt']),

                    CustomFilter::make('typedRangeOperator', function ($query, array $value): void {
                        $query->seen = $value;
                    })->operators([
                        'range' => Type\Obj::make()
                            ->property('min', Type\Integer::make())
                            ->property('max', Type\Integer::make()),
                        'eq' => Type\Integer::make(),
                    ]),

                    CustomFilter::make('score', function ($query, array $value): void {
                        if (isset($value['gt'])) {
                            $query->models = array_filter(
                                $query->models,
                                fn($model) => $model->score > $value['gt'],
                            );

                            return;
                        }

                        $query->models = array_filter(
                            $query->models,
                            fn($model) => (float) $model->score === $value['eq'],
                        );
                    })
                        ->type(Type\Number::make())
                        ->operators(['eq', 'gt']),

                    CustomFilter::make('raw', function ($query, mixed $value): void {
                        $query->seen = $value;
                    }),
                ],
            ),
        );
    }

    public function test_typed_filter_values_are_normalized_before_apply(): void
    {
        $response = $this->api->handle($this->buildRequest('GET', '/items?filter[active]=1'));

        $document = json_decode($response->getBody(), true);

        $this->assertSame(['1'], array_column($document['data'], 'id'));
    }

    public function test_related_queries_use_the_related_collections_filter_types(): void
    {
        $children = [
            (object) ['id' => '1', 'status' => 'published'],
            (object) ['id' => '2', 'status' => 'draft'],
        ];
        $this->api->resource(new MockResource(
            'children',
            models: $children,
            filters: [CustomFilter::make('status')->type(Type\Str::make())],
        ));
        $this->api->resource(new class(
            'parents',
            models: [(object) ['id' => '1', 'children' => $children]],
            endpoints: [ShowRelated::make()],
            fields: [ToMany::make('children')],
            filters: [CustomFilter::make('status')->type(Type\Integer::make())],
        ) extends MockResource {
            public function relatedQuery(object $model, ToMany $relationship, Context $context): ?object
            {
                $query = parent::relatedQuery($model, $relationship, $context);
                $query->models = array_filter(
                    $query->models,
                    fn($child) => $child->status === $context->filter('status'),
                );

                return $query;
            }
        });

        $response = $this->api->handle($this->buildRequest('GET', '/parents/1/children?filter[status]=published'));

        $document = json_decode($response->getBody(), true);
        $this->assertSame(['1'], array_column($document['data'], 'id'));
    }

    public function test_context_normalizes_groups_operators_and_typed_values(): void
    {
        $request = $this->buildRequest('GET', '/')->withQueryParams([
            'filter' => [
                'ids' => '1,2',
                'status' => 'published',
                'created' => '2026-09-24',
                'or' => [['active' => '0'], ['score' => ['gt' => '10']]],
            ],
        ]);
        $context = (new Context($this->api, $request))
            ->withCollection($this->api->getResource('items'))
            ->withParameters([
                Parameter::make('preview')->default(fn(Context $context) => $context->filters()),
                $this->filterParameter(),
            ]);

        $this->assertSame([1, 2], $context->filter('ids'));
        $this->assertSame(FilteringTestStatus::Published, $context->filter('status'));
        $this->assertSame('2026-09-24', $context->filter('created')[0]->format('Y-m-d'));
        $this->assertSame([['active' => false], ['score' => ['gt' => 10.0]]], $context->filter('or'));

        $otherCollection = new MockResource('other', filters: [
            CustomFilter::make('ids')->type(Type\Str::make()),
        ]);
        $this->assertSame('1,2', $context->withCollection($otherCollection)->filter('ids'));
    }

    public function test_duplicate_filter_names_are_rejected(): void
    {
        $this->api->resource(new MockResource(
            'variants',
            endpoints: [Index::make()],
            filters: [
                CustomFilter::make('status')->type(Type\Str::make()),
                CustomFilter::make('status')->type(Type\Integer::make())->hidden(),
            ],
        ));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Filter 'status' is defined more than once in collection 'variants'.");

        $this->api->handle($this->buildRequest('GET', '/variants?filter[status]=12'));
    }

    public function test_hidden_filters_are_rejected(): void
    {
        $this->api->resource(new MockResource(
            'restricted',
            endpoints: [Index::make()],
            filters: [CustomFilter::make('active')->hidden()],
        ));

        try {
            $this->api->handle($this->buildRequest('GET', '/restricted?filter[active]=0'));
            $this->fail('Expected the unavailable filter to be rejected.');
        } catch (UnknownFilterException $e) {
            $this->assertSame('filter[active]', $e->getJsonApiError()['source']['parameter']);
        }
    }

    public function test_context_filter_errors_identify_the_request_parameter(): void
    {
        $context = $this->filterContext(['or' => [['ids' => 'nope']]])
            ->withCollection($this->api->getResource('items'));

        try {
            $context->filters();
            $this->fail('Expected invalid filter values to be rejected.');
        } catch (JsonApiErrorsException $e) {
            $this->assertSame('filter[or][0][ids][0]', $e->errors[0]->getJsonApiError()['source']['parameter']);
        }
    }

    public function test_context_filters_fall_back_to_validated_request_filter(): void
    {
        $context = $this->filterContext(['request' => 'value']);

        $this->assertSame(['request' => 'value'], $context->filters());
        $this->assertSame('value', $context->filter('request'));
        $this->assertNull($context->filter('missing'));
    }

    public function test_delegated_filter_visibility_and_callbacks_see_complete_typed_bag(): void
    {
        $seen = [];
        $filters = [
            'active' => '1',
            'or' => [
                ['ids' => '1'],
                ['not' => ['active' => '0']],
            ],
        ];
        $expected = ['active' => true, 'or' => [['ids' => [1]], ['not' => ['active' => false]]]];
        $capture = function ($query, $value, Context $context) use (&$seen): void {
            $seen[] = $context->filters();
        };
        $resource = new MockResource('grouped', filters: [
            CustomFilter::make('active', $capture)
                ->type(Type\Boolean::make())
                ->visible(
                    fn(Context $context) => $context->filters() === $expected && $context->filter('active') === true,
                ),
            CustomFilter::make('ids', $capture)->type(Type\Arr::make()->items(Type\Integer::make())),
        ]);

        \Tobyz\JsonApiServer\apply_filters(
            $this->query(),
            $filters,
            $resource,
            $this->filterContext(['request' => 'value']),
        );

        $this->assertSame([$expected, $expected, $expected], $seen);
    }

    public function test_explicit_empty_active_filters_do_not_fall_back_to_request_filter(): void
    {
        $context = $this->filterContext(['request' => 'value']);
        $context = $context->withFilters([]);

        $this->assertSame([], $context->filters());
        $this->assertNull($context->filter('request'));
    }

    public function test_replacing_request_or_parameters_clears_active_filters(): void
    {
        $context = $this
            ->filterContext(['ids' => '1'])
            ->withCollection($this->api->getResource('items'))
            ->withFilters(['ids' => '2']);
        $this->assertSame([2], $context->filter('ids'));

        $replaced = $context->withRequest($context->request->withQueryParams(['filter' => ['ids' => '3']]));

        $this->assertSame([], $replaced->filters());
        $this->assertSame([3], $replaced->withParameters([$this->filterParameter()])->filter('ids'));
        $this->assertSame([2], $context->filter('ids'));
        $this->assertSame([1], $context->withParameters([$this->filterParameter()])->filter('ids'));
        $this->assertSame([4], $context->withFilters(['ids' => '4'])->filter('ids'));
    }

    public function test_custom_filter_handler_can_be_defined_after_type(): void
    {
        $query = $this->query();
        $filter = CustomFilter::make('active')
            ->type(Type\Boolean::make())
            ->filter(function ($query, bool $value): void {
                $query->seen = $value;
            });

        $filter->apply(
            $query,
            '1',
            new \Tobyz\JsonApiServer\Context($this->api, $this->buildRequest('GET', '/')),
        );

        $this->assertTrue($query->seen);
    }

    public function test_custom_filter_without_handler_is_noop(): void
    {
        $query = $this->query();
        $filter = CustomFilter::make('active')->type(Type\Boolean::make());

        $filter->apply(
            $query,
            '1',
            new \Tobyz\JsonApiServer\Context($this->api, $this->buildRequest('GET', '/')),
        );

        $this->assertObjectNotHasProperty('seen', $query);
    }

    public function test_comma_separated_array_filters_are_normalized(): void
    {
        $response = $this->api->handle($this->buildRequest('GET', '/items?filter[ids]=1,3'));

        $document = json_decode($response->getBody(), true);

        $this->assertSame(['1', '3'], array_column($document['data'], 'id'));
    }

    public function test_array_filter_items_are_normalized(): void
    {
        $query = $this->query();

        $this->applyFilters($query, ['created' => ['2024-01-01']]);

        $this->assertCount(1, $query->seen);
        $this->assertInstanceOf(\DateTime::class, $query->seen[0]);
        $this->assertSame('2024-01-01', $query->seen[0]->format('Y-m-d'));
    }

    public function test_enum_filter_value_is_normalized_to_case(): void
    {
        $query = $this->query();

        $this->applyFilters($query, ['status' => 'published']);

        $this->assertSame(FilteringTestStatus::Published, $query->seen);
    }

    public function test_object_filter_properties_are_normalized(): void
    {
        $query = $this->query();

        $this->applyFilters($query, [
            'range' => [
                'min' => '1',
                'max' => '2',
                'average' => '1.5',
            ],
        ]);

        $this->assertSame(1, $query->seen['min']);
        $this->assertSame(2, $query->seen['max']);
        $this->assertSame(1.5, $query->seen['average']);
    }

    public function test_object_typed_operator_filter_defaults_object_payload(): void
    {
        $query = $this->query();

        $this->applyFilters($query, [
            'rangeOperator' => [
                'min' => '1',
                'max' => '2',
            ],
        ]);

        $this->assertSame(['eq' => ['min' => 1, 'max' => 2]], $query->seen);
    }

    public function test_object_typed_operator_filter_accepts_explicit_operator_payload(): void
    {
        $query = $this->query();

        $this->applyFilters($query, [
            'rangeOperator' => [
                'gt' => [
                    'min' => '1',
                    'max' => '2',
                ],
            ],
        ]);

        $this->assertSame(['gt' => ['min' => 1, 'max' => 2]], $query->seen);
    }

    public function test_object_typed_default_operator_payload_accepts_bare_value(): void
    {
        $query = $this->query();

        $this->applyFilters($query, [
            'typedRangeOperator' => [
                'min' => '1',
                'max' => '2',
            ],
        ]);

        $this->assertSame(['range' => ['min' => 1, 'max' => 2]], $query->seen);
    }

    public function test_object_typed_default_operator_payload_accepts_explicit_operator(): void
    {
        $query = $this->query();

        $this->applyFilters($query, [
            'typedRangeOperator' => [
                'range' => [
                    'min' => '1',
                    'max' => '2',
                ],
            ],
        ]);

        $this->assertSame(['range' => ['min' => 1, 'max' => 2]], $query->seen);
    }

    public function test_bare_operator_filter_value_uses_default_operator(): void
    {
        $response = $this->api->handle($this->buildRequest('GET', '/items?filter[score]=20'));

        $document = json_decode($response->getBody(), true);

        $this->assertSame(['2'], array_column($document['data'], 'id'));
    }

    public function test_explicit_operator_filter_value_is_normalized(): void
    {
        $response = $this->api->handle($this->buildRequest('GET', '/items?filter[score][gt]=20'));

        $document = json_decode($response->getBody(), true);

        $this->assertSame(['3'], array_column($document['data'], 'id'));
    }

    public function test_operators_can_configure_individual_payload_types(): void
    {
        $query = $this->query();

        $this->applyFilters($query, [
            'createdAt' => [
                'gt' => '2024-01-01',
                'null' => 'false',
            ],
        ]);

        $this->assertInstanceOf(\DateTime::class, $query->seen['gt']);
        $this->assertSame('2024-01-01', $query->seen['gt']->format('Y-m-d'));
        $this->assertFalse($query->seen['null']);
    }

    public function test_untyped_filter_receives_raw_value(): void
    {
        $query = $this->query();

        $this->applyFilters($query, ['raw' => '1']);

        $this->assertSame('1', $query->seen);
    }

    public function test_typed_filters_work_inside_boolean_groups(): void
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/items?filter[or][0][active]=1&filter[or][1][ids]=3'),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertSame(['1', '3'], array_column($document['data'], 'id'));
    }

    public function test_invalid_typed_filter_value_returns_parameter_source(): void
    {
        try {
            $this->api->handle($this->buildRequest('GET', '/items?filter[active]=sometimes'));
        } catch (JsonApiErrorsException $e) {
            $error = $e->errors[0];

            $this->assertSame('Value must be boolean', $error->getMessage());
            $this->assertSame('filter[active]', $error->getJsonApiError()['source']['parameter']);

            return;
        }

        $this->fail('Expected a JSON:API errors exception.');
    }

    public function test_falsy_scalar_filter_parameter_is_invalid(): void
    {
        try {
            $this->api->handle($this->buildRequest('GET', '/items')->withQueryParams(['filter' => '0']));
        } catch (JsonApiErrorsException $e) {
            $error = $e->errors[0];

            $this->assertSame('Value must be object', $error->getMessage());
            $this->assertSame('filter', $error->getJsonApiError()['source']['parameter']);

            return;
        }

        $this->fail('Expected a JSON:API errors exception.');
    }

    public function test_invalid_typed_array_item_returns_parameter_source(): void
    {
        try {
            $this->api->handle($this->buildRequest('GET', '/items?filter[ids]=nope'));
        } catch (JsonApiErrorsException $e) {
            $error = $e->errors[0];

            $this->assertSame('Value must be integer', $error->getMessage());
            $this->assertSame('filter[ids][0]', $error->getJsonApiError()['source']['parameter']);

            return;
        }

        $this->fail('Expected a JSON:API errors exception.');
    }

    public function test_unsupported_operator_returns_parameter_source(): void
    {
        try {
            $this->api->handle($this->buildRequest('GET', '/items?filter[score][lte]=20'));
        } catch (BadRequestException $e) {
            $this->assertSame('Unsupported operator: lte', $e->getMessage());
            $this->assertSame('filter[score][lte]', $e->getJsonApiError()['source']['parameter']);

            return;
        }

        $this->fail('Expected a bad request exception.');
    }

    public function test_invalid_operator_typed_filter_value_returns_parameter_source(): void
    {
        try {
            $this->api->handle($this->buildRequest('GET', '/items?filter[createdAt][gt]=nope'));
        } catch (JsonApiErrorsException $e) {
            $error = $e->errors[0];

            $this->assertSame('Value must be date', $error->getMessage());
            $this->assertSame(
                'filter[createdAt][gt]',
                $error->getJsonApiError()['source']['parameter'],
            );

            return;
        }

        $this->fail('Expected a JSON:API errors exception.');
    }

    private function applyFilters(object $query, array $filters): void
    {
        \Tobyz\JsonApiServer\apply_filters(
            $query,
            $filters,
            $this->api->getResource('items'),
            new \Tobyz\JsonApiServer\Context($this->api, $this->buildRequest('GET', '/')),
        );
    }

    private function filterContext(array $filters): Context
    {
        return (
            new Context(
                $this->api,
                $this->buildRequest('GET', '/')->withQueryParams(['filter' => $filters]),
            )
        )->withParameters([$this->filterParameter()]);
    }

    private function filterParameter(): Parameter
    {
        return Parameter::make('filter')->type(Type\Obj::make());
    }

    private function query(): object
    {
        return (object) ['models' => []];
    }
}

enum FilteringTestStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
