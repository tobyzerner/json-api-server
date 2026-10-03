<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use InvalidArgumentException;
use LogicException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Filter\UnsupportedFilterOperatorException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Laravel\Filter\Where;
use Tobyz\JsonApiServer\Laravel\Filter\WhereBelongsTo;
use Tobyz\JsonApiServer\Laravel\Filter\WhereCount;
use Tobyz\JsonApiServer\Laravel\Filter\WhereHas;
use Tobyz\JsonApiServer\Laravel\Filter\WhereNull;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;

/**
 * Filter configuration, schemas, and validation, which don't need a database.
 * How filters query is tested against Eloquent in EloquentFilterTest.
 */
class FilterSchemaTest extends AbstractTestCase
{
    private const COMPARABLE_OPERATORS = ['eq', 'ne', 'in', 'notin', 'lt', 'lte', 'gt', 'gte', 'null', 'notnull'];
    private const ALL_OPERATORS = [...self::COMPARABLE_OPERATORS, 'like', 'notlike'];

    public function test_where_derives_default_operators_from_integer_type(): void
    {
        $schema = Where::make('age')
            ->type(Type\Integer::make())
            ->getSchema();

        $this->assertSame(self::COMPARABLE_OPERATORS, $schema['x-jsonapi-filter-operators']);
        $this->assertArrayNotHasKey('like', $schema['oneOf'][1]['properties']);
        $this->assertSame(['type' => 'boolean'], $schema['oneOf'][1]['properties']['null']);
    }

    public function test_where_derives_default_operators_from_date_time_type(): void
    {
        $schema = Where::make('createdAt')
            ->type(Type\DateTime::make())
            ->getSchema();

        $this->assertSame(self::COMPARABLE_OPERATORS, $schema['x-jsonapi-filter-operators']);
        $this->assertArrayNotHasKey('like', $schema['oneOf'][1]['properties']);
    }

    public function test_where_derives_default_operators_from_string_type(): void
    {
        $schema = Where::make('name')
            ->type(Type\Str::make())
            ->getSchema();

        $this->assertSame(self::ALL_OPERATORS, $schema['x-jsonapi-filter-operators']);
    }

    public function test_where_derives_default_operators_from_boolean_type(): void
    {
        $schema = Where::make('active')
            ->type(Type\Boolean::make())
            ->getSchema();

        $this->assertSame(['eq', 'ne', 'null', 'notnull'], $schema['x-jsonapi-filter-operators']);
    }

    public function test_where_derives_default_operators_from_array_items(): void
    {
        $schema = Where::make('name')
            ->type(Type\Arr::make()->items(Type\Str::make()))
            ->getSchema();

        $this->assertSame(self::ALL_OPERATORS, $schema['x-jsonapi-filter-operators']);
        $this->assertSame(['type' => 'string'], $schema['oneOf'][1]['properties']['like']);
    }

    public function test_where_rejects_non_default_operators_for_type(): void
    {
        $this->expectException(UnsupportedFilterOperatorException::class);

        Where::make('createdAt')
            ->type(Type\DateTime::make())
            ->apply(new class {}, ['like' => '2024-%'], $this->context());
    }

    public function test_where_explicit_operators_can_opt_into_broader_supported_set(): void
    {
        $schema = Where::make('createdAt')
            ->type(Type\DateTime::make())
            ->operators(['like' => Type\Str::make()])
            ->getSchema();

        $this->assertSame(['like'], $schema['x-jsonapi-filter-operators']);
        $this->assertSame(['type' => 'string'], $schema['oneOf'][1]['properties']['like']);
    }

    public function test_where_preserves_default_null_operator_type_when_narrowed(): void
    {
        $schema = Where::make('publishedAt')
            ->type(Type\Date::make())
            ->operators(['null'])
            ->getSchema();

        $this->assertSame(['type' => 'boolean'], $schema['oneOf'][1]['properties']['null']);
    }

    public function test_where_explicit_operators_can_remove_null_operators(): void
    {
        $schema = Where::make('publishedAt')
            ->type(Type\Date::make())
            ->operators(['eq', 'gt'])
            ->getSchema();

        $this->assertSame(['eq', 'gt'], $schema['x-jsonapi-filter-operators']);
        $this->assertArrayNotHasKey('null', $schema['oneOf'][1]['properties']);
    }

    public function test_where_type_preserves_explicit_operators(): void
    {
        $schema = Where::make('publishedAt')
            ->operators(['null' => Type\Str::make()])
            ->type(Type\Date::make())
            ->getSchema();

        $this->assertSame(['null'], $schema['x-jsonapi-filter-operators']);
        $this->assertSame(['type' => 'string'], $schema['oneOf'][1]['properties']['null']);
    }

    public function test_where_comma_separated_type_preserves_explicit_operators(): void
    {
        $schema = Where::make('id')
            ->operators(['eq'])
            ->type(
                Type\Arr::make()
                    ->items(Type\Integer::make())
                    ->commaSeparated(),
            )
            ->getSchema();

        $this->assertSame(['eq'], $schema['x-jsonapi-filter-operators']);
        $this->assertArrayNotHasKey('gt', $schema['oneOf'][1]['properties']);
    }

    public function test_where_rejects_invalid_comma_separated_items(): void
    {
        $this->expectException(JsonApiErrorsException::class);

        Where::make('id')
            ->type(
                Type\Arr::make()
                    ->items(Type\Integer::make())
                    ->commaSeparated(),
            )
            ->apply(new class {}, '1,nope', $this->context());
    }

    public function test_where_requires_binding_support_for_bound_column_expressions(): void
    {
        $this->expectException(LogicException::class);

        Where::make('date')
            ->column(['DATE(?)', ['UTC']])
            ->apply(new class {
                public function whereIn(...$arguments): void {}
            }, '2024-01-01', $this->context());
    }

    public function test_where_rejects_malformed_column_expressions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Where::make('date')
            ->column(['DATE(?)', [['nested']]])
            ->apply(new class {}, '2024-01-01', $this->context());
    }

    public function test_where_null_schema_uses_boolean_payload(): void
    {
        $schema = WhereNull::make('publishedAt')->getSchema();

        $this->assertSame(['type' => 'boolean'], $schema['oneOf'][0]);
        $this->assertSame(['type' => 'boolean'], $schema['oneOf'][1]['properties']['null']);
    }

    public function test_where_belongs_to_keeps_specialized_operator_set(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WhereBelongsTo::make('author')->operators(['like']);
    }

    public function test_where_count_rejects_non_integer_values(): void
    {
        $this->expectException(JsonApiErrorsException::class);

        WhereCount::make('comments')->apply(new class {}, ['gte' => '2.5'], $this->context());
    }

    public function test_where_has_schema_allows_nested_default_object_payloads(): void
    {
        $schema = WhereHas::make('author')->getSchema();

        $this->assertSame($schema['oneOf'][1], $schema['oneOf'][0]['not']);
    }

    private function context(): Context
    {
        return new Context(new JsonApi(), $this->buildRequest('GET', '/'));
    }
}
