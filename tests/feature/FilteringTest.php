<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
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
                fields: [
                    Attribute::make('active'),
                    Attribute::make('score'),
                ],
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
                    })->type(Type\Arr::make()->items(Type\Integer::make())->commaSeparated()),

                    CustomFilter::make('created', function ($query, array $value): void {
                        $query->seen = $value;
                    })->type(Type\Arr::make()->items(Type\Date::make())),

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

    private function applyFilters(object $query, array $filters): void
    {
        \Tobyz\JsonApiServer\apply_filters(
            $query,
            $filters,
            $this->api->getResource('items'),
            new \Tobyz\JsonApiServer\Context($this->api, $this->buildRequest('GET', '/')),
        );
    }

    private function query(): object
    {
        return (object) ['models' => []];
    }
}
