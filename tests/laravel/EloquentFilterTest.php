<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Laravel\Field\ToMany;
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
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Id;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\laravel\Models\Comment;
use Tobyz\Tests\JsonApiServer\laravel\Models\Post;
use Tobyz\Tests\JsonApiServer\laravel\Models\User;

class EloquentFilterTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $toby = User::create(['name' => 'Toby', 'is_admin' => true]);
        $franz = User::create(['name' => 'Franz']);

        Post::create([
            'title' => 'Apple',
            'user_id' => $toby->id,
            'published' => true,
            'published_at' => '2024-01-01 00:00:00',
        ]);
        Post::create(['title' => 'Banana', 'user_id' => $franz->id, 'published' => false]);
        Post::create([
            'title' => 'Cherry',
            'published' => true,
            'published_at' => '2024-06-01 00:00:00',
        ]);

        Comment::create(['body' => 'One', 'post_id' => 1]);
        Comment::create(['body' => 'Two', 'post_id' => 1]);
        Comment::create(['body' => 'Three', 'post_id' => 2]);
    }

    public function test_where_equals_and_in()
    {
        $this->posts([Where::make('title')]);

        $this->assertSame(['1'], $this->filter('filter[title]=Apple'));
        $this->assertSame(
            ['1', '2'],
            $this->filter('filter[title][in][]=Apple&filter[title][in][]=Banana'),
        );
        $this->assertSame(
            ['3'],
            $this->filter('filter[title][notin][]=Apple&filter[title][notin][]=Banana'),
        );
        $this->assertSame(['2', '3'], $this->filter('filter[title][ne]=Apple'));
    }

    public function test_where_comparisons_on_typed_values()
    {
        $this->posts([Where::make('id')->type(Type\Integer::make())]);

        $this->assertSame(['2', '3'], $this->filter('filter[id][gt]=1'));
        $this->assertSame(['1', '2'], $this->filter('filter[id][lte]=2'));
        $this->assertSame(['2'], $this->filter('filter[id][gte]=2&filter[id][lt]=3'));
    }

    public function test_where_dates()
    {
        $this->posts([Where::make('publishedAt')->type(Type\DateTime::make())]);

        $this->assertSame(['3'], $this->filter('filter[publishedAt][gt]=2024-03-01T00:00:00Z'));
        $this->assertSame(['2'], $this->filter('filter[publishedAt][null]=true'));
        $this->assertSame(['1', '3'], $this->filter('filter[publishedAt][notnull]=true'));
    }

    public function test_where_like()
    {
        $this->posts([Where::make('title')]);

        $this->assertSame(['2'], $this->filter('filter[title][like]=Ban%'));
        $this->assertSame(['1', '3'], $this->filter('filter[title][notlike]=Ban%'));
    }

    public function test_where_boolean()
    {
        $this->posts([Where::make('published')->asBoolean()]);

        $this->assertSame(['1', '3'], $this->filter('filter[published]=1'));
        $this->assertSame(['2'], $this->filter('filter[published]=0'));
    }

    public function test_where_typed_and_comma_separated_lists()
    {
        $this->posts([
            Where::make('id')->type(Type\Integer::make()),
            Where::make('ids')
                ->column('id')
                ->type(
                    Type\Arr::make()
                        ->items(Type\Integer::make())
                        ->commaSeparated(),
                ),
        ]);

        $this->assertSame(['1', '3'], $this->filter('filter[id][in][]=1&filter[id][in][]=3'));
        $this->assertSame(['1', '3'], $this->filter('filter[ids]=1,3'));
    }

    public function test_where_null_operator_flags()
    {
        $this->posts([
            Where::make('publishedAt'),
            Where::make('publishedOn')
                ->column('published_at')
                ->type(Type\Date::make())
                ->operators(['null']),
        ]);

        $this->assertSame(['1', '3'], $this->filter('filter[publishedAt][null]=false'));
        $this->assertSame(['2'], $this->filter('filter[publishedAt][notnull]=false'));
        $this->assertSame(['1', '3'], $this->filter('filter[publishedOn][null]=false'));
    }

    public function test_where_null_and_not_null()
    {
        $this->posts([
            WhereNull::make('draft')->column('published_at'),
            WhereNotNull::make('scheduled')->column('published_at'),
        ]);

        $this->assertSame(['2'], $this->filter('filter[draft]=true'));
        $this->assertSame(['1', '3'], $this->filter('filter[draft]=false'));
        $this->assertSame(['1', '3'], $this->filter('filter[scheduled]=true'));
        $this->assertSame(['2'], $this->filter('filter[scheduled]=false'));
        $this->assertSame(['2'], $this->filter('filter[draft][null]=true'));
        $this->assertSame(['1', '3'], $this->filter('filter[scheduled][notnull]=true'));
    }

    public function test_where_boolean_type_without_boolean_mode()
    {
        $this->posts([Where::make('published')->type(Type\Boolean::make())]);

        $this->assertSame(['2'], $this->filter('filter[published][eq]=false'));
        $this->assertSame(['1', '3'], $this->filter('filter[published][ne]=false'));
    }

    public function test_where_column_expressions()
    {
        $seenContext = null;

        $this->posts([
            Where::make('shout')->column(function (Context $context) use (&$seenContext) {
                $seenContext = $context;

                return ['title || ?', ['!']];
            }),
            Where::make('live')
                ->column(['COALESCE(published, ?)', [0]])
                ->asBoolean(),
        ]);

        // Each operator repeats the expression's bindings. The Eloquent builder
        // only accepts bindings by forwarding addBinding() to its base query.
        $this->assertSame(
            ['1', '3'],
            $this->filter('filter[shout][like]=%25!&filter[shout][notlike]=%25a!'),
        );
        $this->assertSame(['2'], $this->filter('filter[shout]=Banana!'));
        $this->assertInstanceOf(Context::class, $seenContext);

        $this->assertSame(['1', '3'], $this->filter('filter[live]=1'));
    }

    public function test_where_belongs_to()
    {
        $this->posts([WhereBelongsTo::make('author')]);

        $this->assertSame(['1'], $this->filter('filter[author]=1'));
        $this->assertSame(['1', '2'], $this->filter('filter[author]=1,2'));
        $this->assertSame(['3'], $this->filter('filter[author][null]=true'));
    }

    public function test_where_belongs_to_operators_and_columns()
    {
        $this->posts([
            WhereBelongsTo::make('author')->type(Type\Integer::make()),
            WhereBelongsTo::make('hasAuthor')->relationship('author')->asBoolean(),
            WhereBelongsTo::make('writer')->column('user_id'),
            WhereBelongsTo::make('hasWriter')
                ->column(['user_id + ?', [0]])
                ->asBoolean(),
        ]);

        $this->assertSame(['1'], $this->filter('filter[author]=1'));
        $this->assertSame(['3'], $this->filter('filter[author][notnull]=false'));
        $this->assertSame(['1', '2'], $this->filter('filter[hasAuthor]=1'));
        $this->assertSame(['3'], $this->filter('filter[hasAuthor]=0'));
        $this->assertSame(['2'], $this->filter('filter[writer]=2'));
        $this->assertSame(['3'], $this->filter('filter[hasWriter]=0'));
    }

    public function test_where_has_by_id_and_nested_filters()
    {
        $this->posts([WhereHas::make('author')], [ToOne::make('author')->type('users')]);

        $this->assertSame(['2'], $this->filter('filter[author]=2'));
        $this->assertSame(['1'], $this->filter('filter[author][name]=Toby'));
        $this->assertSame(['2', '3'], $this->filter('filter[author][ne]=1'));
        $this->assertSame(['3'], $this->filter('filter[author][null]=true'));
        $this->assertSame(['1', '2'], $this->filter('filter[author][notnull]=true'));
    }

    #[DataProvider('authorTypes')]
    public function test_where_has_by_id_attribute_when_it_is_not_the_key(string $type)
    {
        User::whereKey(2)->update(['uuid' => 'uuid-franz']);

        $this->resources(
            fields: ['posts' => [Attribute::make('title'), ToOne::make('author')->type($type)]],
            filters: ['posts' => [WhereHas::make('author')]],
            ids: ['users' => Id::make()->property('uuid')],
        );
        $this->api->collection(new TestCollection('people', ['users']));

        $this->assertSame(['2'], $this->filter('filter[author]=uuid-franz'));
        $this->assertSame(['1', '3'], $this->filter('filter[author][ne]=uuid-franz'));
    }

    public static function authorTypes(): array
    {
        return ['resource' => ['users'], 'collection' => ['people']];
    }

    public function test_where_has_typed_ids()
    {
        $this->posts([WhereHas::make('author')->type(Type\Integer::make())], [ToOne::make(
            'author',
        )->type('users')]);

        $this->assertSame(['2', '3'], $this->filter('filter[author][ne]=1'));
        $this->assertInstanceOf(JsonApiErrorsException::class, $this->exception(
            fn() => $this->filter('filter[author][ne]=abc'),
        ));
    }

    public function test_where_has_passes_filter_bags_to_related_filters_and_scope()
    {
        $nested = null;
        $scoped = null;

        $this->posts(
            [WhereHas::make('author'), Where::make('published')->asBoolean()],
            [ToOne::make('author')->type('users')],
            usersScope: function ($query, Context $context) use (&$scoped) {
                $scoped = $context->filters();
            },
            usersFilters: [
                CustomFilter::make('name', function ($query, string $value, Context $context) use (
                    &$nested,
                ) {
                    $nested = $context->filters();
                    $query->where('name', $value);
                }),
            ],
        );

        $this->assertSame(['1'], $this->filter('filter[author][name]=Toby&filter[published]=1'));
        $this->assertSame(['name' => 'Toby'], $nested);
        $this->assertSame(['author' => ['eq' => ['name' => 'Toby']], 'published' => true], $scoped);
    }

    public function test_where_has_applies_related_and_field_scopes()
    {
        $this->posts(
            [WhereHas::make('author'), WhereHas::make('comments')],
            [
                ToOne::make('author')->type('users'),
                ToMany::make('comments')
                    ->type('comments')
                    ->scope(fn($query) => $query->where('body', '!=', 'Three')),
            ],
            usersScope: fn($query) => $query->where('is_admin', false),
        );

        // Toby is an admin, so the users scope hides him.
        $this->assertSame([], $this->filter('filter[author]=1'));
        $this->assertSame(['1', '3'], $this->filter('filter[author][null]=true'));

        // The only comment on post 2 is excluded by the field scope.
        $this->assertSame(['1'], $this->filter('filter[comments][notnull]=true'));
    }

    public function test_where_has_related_scopes_see_the_relationship()
    {
        $fields = [];
        $record = function ($query, Context $context) use (&$fields) {
            $fields[] = $context->field;
        };

        $author = ToOne::make('author')->type('users')->scope($record);

        $this->posts([WhereHas::make('author')], [$author], usersScope: $record);

        $this->filter('filter[author]=1');

        $this->assertSame([$author, $author], $fields);
    }

    public function test_where_has_applies_related_list_scope_only_to_to_many()
    {
        $this->resources(
            fields: ['posts' => [
                ToOne::make('author')->type('users'),
                ToMany::make('comments')->type('comments'),
            ]],
            listScopes: [
                'users' => fn($query) => $query->where('is_admin', false),
                'comments' => fn($query) => $query->where('body', '!=', 'One'),
            ],
            filters: ['posts' => [WhereHas::make('author'), WhereHas::make('comments')]],
        );

        $this->assertSame(['1'], $this->filter('filter[author]=1'));
        $this->assertSame([], $this->filter('filter[comments]=1'));
    }

    public function test_where_count()
    {
        $this->posts([
            WhereCount::make('comments'),
            WhereCount::make('otherComments')
                ->relationship('comments')
                ->scope(fn($query) => $query->where('body', 'One')),
        ]);

        $this->assertSame(['1'], $this->filter('filter[comments][gte]=2'));
        $this->assertSame(['2'], $this->filter('filter[comments]=1'));
        $this->assertSame(['1', '3'], $this->filter('filter[comments][ne]=1'));
        $this->assertSame(['1'], $this->filter('filter[otherComments]=1'));
    }

    public function test_where_exists()
    {
        $this->posts([
            WhereExists::make('comments'),
            WhereExists::make('hasOne')
                ->relationship('comments')
                ->scope(fn($query) => $query->where('body', 'One')),
        ]);

        $this->assertSame(['1', '2'], $this->filter('filter[comments]=1'));
        $this->assertSame(['3'], $this->filter('filter[comments]=0'));
        $this->assertSame(['1'], $this->filter('filter[hasOne]=1'));
    }

    public function test_scope_filter()
    {
        $this->posts([
            Scope::make('titled'),
            Scope::make('startsWith')->scope(fn(Builder $query, $value) => $query->where(
                'title',
                'like',
                "$value%",
            )),
            Scope::make('published')->asBoolean(),
        ]);

        $this->assertSame(['2'], $this->filter('filter[titled]=Banana'));
        $this->assertSame(['1', '3'], $this->filter('filter[titled][ne]=Banana'));
        $this->assertSame(['3'], $this->filter('filter[startsWith]=Ch'));
        $this->assertSame(['1', '3'], $this->filter('filter[published]=1'));
        $this->assertSame(['2'], $this->filter('filter[published]=0'));
    }

    public function test_scope_filter_types()
    {
        $values = [];

        $this->posts([
            Scope::make('ids')
                ->scope(fn(Builder $query, array $ids) => $query->whereIn('id', $ids))
                ->type(
                    Type\Arr::make()
                        ->items(Type\Integer::make())
                        ->commaSeparated(),
                ),
            Scope::make('since')
                ->scope(function (Builder $query, $value) use (&$values) {
                    $values[] = $value;
                })
                ->operators(['eq' => Type\Date::make(), 'ne' => Type\Integer::make()]),
        ]);

        $this->assertSame(['1', '3'], $this->filter('filter[ids]=1,3'));

        $this->filter('filter[since][eq]=2024-01-01&filter[since][ne]=2');

        $this->assertInstanceOf(\DateTimeInterface::class, $values[0]);
        $this->assertSame(2, $values[1]);
    }

    public function test_boolean_filter_groups()
    {
        $this->posts([Where::make('title'), Where::make('published')->asBoolean()]);

        $this->assertSame(
            ['1', '2'],
            $this->filter('filter[or][0][title]=Apple&filter[or][1][title]=Banana'),
        );
        $this->assertSame(['2', '3'], $this->filter('filter[not][title]=Apple'));
        $this->assertSame(
            ['3'],
            $this->filter(
                'filter[published]=1&filter[not][or][0][title]=Apple&filter[not][or][1][title]=Banana',
            ),
        );
    }

    /**
     * @return string[] IDs of the posts matching a filter query string.
     */
    private function filter(string $query): array
    {
        return $this->ids($this->get("/posts?$query"));
    }

    private function posts(
        array $filters,
        array $fields = [],
        ?Closure $usersScope = null,
        array $usersFilters = [],
    ): void {
        $this->resources(
            fields: ['posts' => [Attribute::make('title'), ...$fields]],
            scopes: ['users' => $usersScope],
            filters: ['posts' => $filters, 'users' => $usersFilters ?: [Where::make('name')]],
        );
    }
}
