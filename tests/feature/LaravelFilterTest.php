<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Closure;
use Nyholm\Psr7\ServerRequest;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Filter\InvalidFilterValueException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Laravel\EloquentResource;
use Tobyz\JsonApiServer\Laravel\Field\ToOne;
use Tobyz\JsonApiServer\Laravel\Filter\Scope;
use Tobyz\JsonApiServer\Laravel\Filter\Where;
use Tobyz\JsonApiServer\Laravel\Filter\WhereBelongsTo;
use Tobyz\JsonApiServer\Laravel\Filter\WhereCount;
use Tobyz\JsonApiServer\Laravel\Filter\WhereExists;
use Tobyz\JsonApiServer\Laravel\Filter\WhereHas;
use Tobyz\JsonApiServer\Laravel\Filter\WhereNotNull;
use Tobyz\JsonApiServer\Laravel\Filter\WhereNull;
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;

class LaravelFilterTest extends AbstractTestCase
{
    public function test_where_filter_applies_boolean_values(): void
    {
        $query = new RecordingQuery();

        Where::make('active')
            ->column('active')
            ->asBoolean()
            ->apply($query, '1', $this->context());

        $this->assertSame(['where', ['active', true]], $query->calls[0]);
    }

    public function test_where_filter_applies_set_operators_with_typed_lists(): void
    {
        $query = new RecordingQuery();

        Where::make('age')
            ->column('age')
            ->type(Type\Integer::make())
            ->apply($query, ['in' => ['1', '2']], $this->context());

        $this->assertSame(['whereIn', ['age', [1, 2]]], $query->calls[0]);
    }

    public function test_where_filter_applies_comparison_values(): void
    {
        $query = new RecordingQuery();

        Where::make('createdAt')
            ->column('created_at')
            ->type(Type\DateTime::make())
            ->apply($query, ['gt' => '2024-01-01T00:00:00+00:00'], $this->context());

        $this->assertSame('where', $query->calls[0][0]);
        $this->assertSame('created_at', $query->calls[0][1][0]);
        $this->assertSame('>', $query->calls[0][1][1]);
        $this->assertInstanceOf(\DateTime::class, $query->calls[0][1][2]);
    }

    public function test_where_filter_applies_like_values(): void
    {
        $query = new RecordingQuery();

        Where::make('name')
            ->column('name')
            ->apply($query, ['notlike' => 'T%'], $this->context());

        $this->assertSame(['where', ['name', 'not like', 'T%']], $query->calls[0]);
    }

    public function test_where_filter_applies_null_operator_flags(): void
    {
        $query = new RecordingQuery();
        $filter = Where::make('publishedAt')->column('published_at');

        $filter->apply($query, ['null' => 'false'], $this->context());
        $filter->apply($query, ['notnull' => 'false'], $this->context());

        $this->assertSame(['whereNotNull', ['published_at']], $query->calls[0]);
        $this->assertSame(['whereNull', ['published_at']], $query->calls[1]);
    }

    public function test_where_null_filter_defaults_to_null_operator(): void
    {
        $query = new RecordingQuery();
        $filter = WhereNull::make('publishedAt')->column('published_at');

        $filter->apply($query, 'true', $this->context());
        $filter->apply($query, 'false', $this->context());

        $this->assertSame(['whereNull', ['published_at']], $query->calls[0]);
        $this->assertSame(['whereNotNull', ['published_at']], $query->calls[1]);
    }

    public function test_where_null_filter_schema_uses_boolean_payload(): void
    {
        $schema = WhereNull::make('publishedAt')->getSchema();

        $this->assertSame(['type' => 'boolean'], $schema['oneOf'][0]);
        $this->assertSame(['type' => 'boolean'], $schema['oneOf'][1]['properties']['null']);
    }

    public function test_where_not_null_filter_defaults_to_not_null_operator(): void
    {
        $query = new RecordingQuery();
        $filter = WhereNotNull::make('publishedAt')->column('published_at');

        $filter->apply($query, 'true', $this->context());
        $filter->apply($query, 'false', $this->context());

        $this->assertSame(['whereNotNull', ['published_at']], $query->calls[0]);
        $this->assertSame(['whereNull', ['published_at']], $query->calls[1]);
    }

    public function test_where_filter_applies_comma_separated_values(): void
    {
        $query = new RecordingQuery();

        Where::make('id')
            ->column('id')
            ->commaSeparated(Type\Integer::make())
            ->apply($query, '1,2', $this->context());

        $this->assertSame(['whereIn', ['id', [1, 2]]], $query->calls[0]);
    }

    public function test_where_filter_uses_existing_type_for_comma_separated_items(): void
    {
        $query = new RecordingQuery();

        Where::make('id')
            ->column('id')
            ->type(Type\Integer::make())
            ->commaSeparated()
            ->apply($query, '1,2', $this->context());

        $this->assertSame(['whereIn', ['id', [1, 2]]], $query->calls[0]);
    }

    public function test_where_filter_preserves_existing_array_items_for_comma_separated_values(): void
    {
        $query = new RecordingQuery();

        Where::make('id')
            ->column('id')
            ->type(Type\Arr::make()->items(Type\Integer::make()))
            ->commaSeparated()
            ->apply($query, '1,2', $this->context());

        $this->assertSame(['whereIn', ['id', [1, 2]]], $query->calls[0]);
    }

    public function test_where_filter_rejects_invalid_existing_type_for_comma_separated_items(): void
    {
        $this->expectException(JsonApiErrorsException::class);

        Where::make('id')
            ->type(Type\Integer::make())
            ->commaSeparated()
            ->apply(new RecordingQuery(), '1,nope', $this->context());
    }

    public function test_where_filter_preserves_comma_separated_scalar_operator_values(): void
    {
        $query = new RecordingQuery();

        Where::make('name')
            ->column('name')
            ->commaSeparated(Type\Str::make())
            ->apply($query, ['like' => 'a,b'], $this->context());

        $this->assertSame(['where', ['name', 'like', 'a,b']], $query->calls[0]);
    }

    public function test_where_belongs_to_filter_uses_relationship_foreign_key(): void
    {
        $query = new BelongsToQuery();

        WhereBelongsTo::make('author')
            ->type(Type\Integer::make())
            ->apply($query, '1', $this->context());

        $this->assertSame(['whereIn', ['posts.author_id', [1]]], $query->calls[0]);
    }

    public function test_where_belongs_to_filter_defaults_to_comma_separated_string_ids(): void
    {
        $query = new BelongsToQuery();

        WhereBelongsTo::make('author')->apply($query, '1,2', $this->context());

        $this->assertSame(['whereIn', ['posts.author_id', ['1', '2']]], $query->calls[0]);
    }

    public function test_where_belongs_to_filter_applies_null_operator_flags(): void
    {
        $query = new BelongsToQuery();

        WhereBelongsTo::make('author')->apply($query, ['notnull' => 'false'], $this->context());

        $this->assertSame(['whereNull', ['posts.author_id']], $query->calls[0]);
    }

    public function test_where_belongs_to_filter_applies_boolean_values_as_existence_checks(): void
    {
        $query = new BelongsToQuery();
        $filter = WhereBelongsTo::make('author')->asBoolean();

        $filter->apply($query, 'true', $this->context());
        $filter->apply($query, 'false', $this->context());

        $this->assertSame(['whereNotNull', ['posts.author_id']], $query->calls[0]);
        $this->assertSame(['whereNull', ['posts.author_id']], $query->calls[1]);
    }

    public function test_where_belongs_to_filter_honors_explicit_column(): void
    {
        $query = new BelongsToQuery();

        WhereBelongsTo::make('author')
            ->column('custom_author_id')
            ->apply($query, '1', $this->context());

        $this->assertSame(['whereIn', ['custom_author_id', ['1']]], $query->calls[0]);
    }

    public function test_scope_filter_applies_eq_and_ne_values(): void
    {
        $query = new ScopeQuery();
        $filter = Scope::make('withId')->scope('withId');

        $filter->apply($query, '1', $this->context());
        $filter->apply($query, ['ne' => '2'], $this->context());

        $this->assertSame(['withId', ['1']], $query->calls[0]);
        $this->assertSame('whereNot', $query->calls[1][0]);
        $this->assertSame(['withId', ['2']], $query->calls[2]);
    }

    public function test_scope_filter_applies_boolean_mode(): void
    {
        $query = new ScopeQuery();
        $filter = Scope::make('active')->asBoolean();

        $filter->apply($query, 'false', $this->context());

        $this->assertSame('whereNot', $query->calls[0][0]);
        $this->assertSame(['active', []], $query->calls[1]);
    }

    public function test_scope_filter_applies_comma_separated_arguments(): void
    {
        $query = new ScopeQuery();

        Scope::make('withIds')
            ->scope('withIds')
            ->commaSeparated(Type\Integer::make())
            ->apply($query, '1,2', $this->context());

        $this->assertSame(['withIds', [[1, 2]]], $query->calls[0]);
    }

    public function test_scope_filter_uses_existing_type_for_comma_separated_arguments(): void
    {
        $query = new ScopeQuery();

        Scope::make('withIds')
            ->scope('withIds')
            ->type(Type\Integer::make())
            ->commaSeparated()
            ->apply($query, '1,2', $this->context());

        $this->assertSame(['withIds', [[1, 2]]], $query->calls[0]);
    }

    public function test_scope_filter_preserves_existing_array_items_for_comma_separated_arguments(): void
    {
        $query = new ScopeQuery();

        Scope::make('withIds')
            ->scope('withIds')
            ->type(Type\Arr::make()->items(Type\Integer::make()))
            ->commaSeparated()
            ->apply($query, '1,2', $this->context());

        $this->assertSame(['withIds', [[1, 2]]], $query->calls[0]);
    }

    public function test_where_exists_filter_applies_boolean_values(): void
    {
        $query = new RecordingQuery();

        WhereExists::make('comments')->apply($query, 'false', $this->context());

        $this->assertSame(['whereDoesntHave', ['comments', null]], $query->calls[0]);
    }

    public function test_where_count_filter_applies_operator_map(): void
    {
        $query = new RecordingQuery();

        WhereCount::make('comments')->apply($query, ['gte' => '2'], $this->context());

        $this->assertSame(['whereHas', ['comments', null, '>=', '2']], $query->calls[0]);
    }

    public function test_where_count_filter_rejects_non_scalar_values(): void
    {
        $this->expectException(InvalidFilterValueException::class);

        WhereCount::make('comments')->apply(
            new RecordingQuery(),
            ['gte' => ['2']],
            $this->context(),
        );
    }

    public function test_where_has_filter_applies_nested_filter_payloads(): void
    {
        $context = $this->relationshipContext();
        $query = new RelationshipQuery();

        WhereHas::make('author')->apply($query, ['name' => 'Toby'], $context);

        $this->assertSame('whereHas', $query->calls[0][0]);
        $this->assertSame('author', $query->calls[0][1][0]);
        $this->assertSame(['name' => 'Toby'], $query->relatedQuery->seen);
    }

    public function test_where_has_filter_applies_typed_id_operator_payloads(): void
    {
        $context = $this->relationshipContext();
        $query = new RelationshipQuery();

        WhereHas::make('author')
            ->type(Type\Integer::make())
            ->apply($query, ['ne' => '1'], $context);

        $this->assertSame('whereDoesntHave', $query->calls[0][0]);
        $this->assertSame([1], $query->relatedQuery->keys);
    }

    public function test_where_has_filter_applies_null_operator_flags(): void
    {
        $context = $this->relationshipContext();
        $query = new RelationshipQuery();

        WhereHas::make('author')->apply($query, ['null' => 'true'], $context);

        $this->assertSame('whereDoesntHave', $query->calls[0][0]);
    }

    public function test_where_has_filter_schema_allows_nested_default_object_payloads(): void
    {
        $schema = WhereHas::make('author')->getSchema();

        $this->assertSame($schema['oneOf'][1], $schema['oneOf'][0]['not']);
    }

    private function context(): Context
    {
        return new Context(new JsonApi(), new ServerRequest('GET', '/'));
    }

    private function relationshipContext(): Context
    {
        $api = new JsonApi();
        $api->resource(
            new FakeEloquentResource('users', [
                CustomFilter::make('name', function ($query, string $value): void {
                    $query->seen = ['name' => $value];
                }),
            ]),
        );
        $resource = new FakeEloquentResource(
            'posts',
            fields: [ToOne::make('author')->type('users')],
        );
        $api->resource($resource);

        return (new Context($api, new ServerRequest('GET', '/')))->withCollection($resource);
    }
}

class RecordingQuery
{
    public array $calls = [];

    public function where(...$arguments): void
    {
        $this->calls[] = [__FUNCTION__, $arguments];
    }

    public function whereIn(...$arguments): void
    {
        $this->calls[] = [__FUNCTION__, $arguments];
    }

    public function whereNotIn(...$arguments): void
    {
        $this->calls[] = [__FUNCTION__, $arguments];
    }

    public function whereNull(...$arguments): void
    {
        $this->calls[] = [__FUNCTION__, $arguments];
    }

    public function whereNotNull(...$arguments): void
    {
        $this->calls[] = [__FUNCTION__, $arguments];
    }

    public function whereHas(...$arguments): void
    {
        $this->calls[] = [__FUNCTION__, $arguments];
    }

    public function whereDoesntHave(...$arguments): void
    {
        $this->calls[] = [__FUNCTION__, $arguments];
    }
}

class ScopeQuery
{
    public array $calls = [];

    public function __call(string $method, array $arguments): void
    {
        $this->calls[] = [$method, $arguments];
    }

    public function where(Closure $callback): void
    {
        $this->calls[] = [__FUNCTION__, []];
        $callback($this);
    }

    public function whereNot(Closure $callback): void
    {
        $this->calls[] = [__FUNCTION__, []];
        $callback($this);
    }
}

class BelongsToQuery extends RecordingQuery
{
    public function getModel(): object
    {
        return new class {
            public function author(): object
            {
                return new class {
                    public function getQualifiedForeignKeyName(): string
                    {
                        return 'posts.author_id';
                    }
                };
            }
        };
    }
}

class RelationshipQuery extends RecordingQuery
{
    public ?RelatedQuery $relatedQuery = null;

    public function whereHas(...$arguments): void
    {
        $this->applyRelationship(__FUNCTION__, $arguments);
    }

    public function whereDoesntHave(...$arguments): void
    {
        $this->applyRelationship(__FUNCTION__, $arguments);
    }

    private function applyRelationship(string $method, array $arguments): void
    {
        $this->calls[] = [$method, $arguments];

        $this->relatedQuery = new RelatedQuery();
        $callback = $arguments[1] ?? null;

        if ($callback) {
            $callback($this->relatedQuery);
        }
    }
}

class RelatedQuery
{
    public ?array $keys = null;
    public ?array $seen = null;

    public function whereKey(array $ids): void
    {
        $this->keys = $ids;
    }
}

class FakeEloquentResource extends EloquentResource
{
    public function __construct(
        private readonly string $type,
        private readonly array $filters = [],
        private readonly array $fields = [],
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function newModel(Context $context): object
    {
        return new class {};
    }

    public function scope($query, Context $context): void
    {
    }
}
