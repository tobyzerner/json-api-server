<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Closure;
use ErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Exception\Pagination\InvalidPageCursorException;
use Tobyz\JsonApiServer\Exception\Pagination\RangePaginationNotSupportedException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Laravel\SoftDeletes;
use Tobyz\JsonApiServer\Laravel\Sort\SortColumn;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Type\DateTime;
use Tobyz\Tests\JsonApiServer\laravel\Models\Post;
use Tobyz\Tests\JsonApiServer\laravel\Models\User;

class EloquentResourceTest extends LaravelTestCase
{
    public function test_lists_models()
    {
        User::create(['name' => 'Toby']);
        User::create(['name' => 'Franz']);

        $this->api->resource($this->users());

        $response = $this->get('/users');

        $this->assertSame(['1', '2'], $this->ids($response));
        $this->assertJsonApiDocumentSubset(
            ['data' => [['type' => 'users', 'attributes' => ['name' => 'Toby']]]],
            $response->getBody(),
        );
    }

    public function test_scope_applies_to_listing_and_finding()
    {
        User::create(['name' => 'Toby', 'is_admin' => true]);
        User::create(['name' => 'Franz']);

        $this->api->resource($this->users(scope: fn($query) => $query->where('is_admin', false)));

        $this->assertSame(['2'], $this->ids($this->get('/users')));
        $this->assertSame(200, $this->get('/users/2')->getStatusCode());
        $this->assertInstanceOf(ResourceNotFoundException::class, $this->exception(fn() => $this->get('/users/1')));
    }

    public function test_scope_receives_context()
    {
        $contexts = [];

        $this->api->resource($this->users(scope: function ($query, Context $context) use (&$contexts) {
            $contexts[] = $context;
        }));

        $this->get('/users');

        $this->assertCount(1, $contexts);
        $this->assertInstanceOf(Context::class, $contexts[0]);
    }

    public function test_attributes_are_read_from_snake_case_or_configured_properties()
    {
        User::create(['name' => 'Toby', 'email' => 'toby@example.com', 'is_admin' => true]);

        $this->api->resource($this->users(fields: [
            Attribute::make('isAdmin'),
            Attribute::make('contact')->property('email'),
        ]));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['isAdmin' => true, 'contact' => 'toby@example.com']]],
            $this->get('/users/1')->getBody(),
        );
    }

    public function test_attribute_getter_overrides_model_value()
    {
        User::create(['name' => 'Toby']);

        $this->api->resource($this->users(fields: [Attribute::make(
            'name',
        )->get(fn(User $user) => strtoupper($user->name))]));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['name' => 'TOBY']]],
            $this->get('/users/1')->getBody(),
        );
    }

    public function test_creates_model()
    {
        $this->api->resource($this->users(fields: [
            Attribute::make('name')->writable(),
            Attribute::make('isAdmin')->writable(),
        ]));

        $response = $this->create('users', ['name' => 'Toby', 'isAdmin' => true]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('1', $this->document($response)['data']['id']);

        $user = User::find(1);
        $this->assertSame('Toby', $user->name);
        $this->assertTrue($user->is_admin);
    }

    public function test_updates_model()
    {
        User::create(['name' => 'Toby']);

        $this->api->resource($this->users(fields: [Attribute::make('name')->writable()]));

        $response = $this->update('users', '1', ['name' => 'Franz']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Franz', User::find(1)->name);
    }

    public function test_deletes_model()
    {
        User::create(['name' => 'Toby']);

        $this->api->resource($this->users());

        $response = $this->send('DELETE', '/users/1', []);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertNull(User::find(1));
    }

    public function test_deleting_a_soft_deleting_model_soft_deletes_it()
    {
        Post::create(['title' => 'Hello']);

        $this->api->resource($this->posts());

        $this->send('DELETE', '/posts/1', []);

        $this->assertNull(Post::find(1));
        $this->assertNotNull(Post::withTrashed()->find(1));
    }

    public function test_soft_deletes_trait_force_deletes()
    {
        Post::create(['title' => 'Hello']);

        $this->api->resource(new class('posts', Post::class, endpoints: [Delete::make()]) extends TestResource {
            use SoftDeletes;
        });

        $this->send('DELETE', '/posts/1', []);

        $this->assertNull(Post::withTrashed()->find(1));
    }

    public function test_create_lifecycle_hooks_run_in_order_and_can_replace_the_model()
    {
        $resource = $this->hookedUsers();
        $this->api->resource($resource);

        $this->create('users', ['name' => 'Toby']);

        $this->assertSame(['creating', 'saving', 'saved', 'created'], $resource->calls);

        // The saving hook replaced the name before the model was saved.
        $this->assertSame('Toby (saving)', User::find(1)->name);
    }

    public function test_update_lifecycle_hooks_run_in_order()
    {
        User::create(['name' => 'Toby']);

        $resource = $this->hookedUsers();
        $this->api->resource($resource);

        $this->update('users', '1', ['name' => 'Franz']);

        $this->assertSame(['updating', 'saving', 'saved', 'updated'], $resource->calls);
        $this->assertSame('Franz (saving)', User::find(1)->name);
    }

    public function test_date_times_are_stored_in_the_app_timezone()
    {
        $this->setConfig('app.timezone', 'Australia/Adelaide');

        Post::create(['title' => 'Hello']);

        $this->api->resource($this->posts(fields: [Attribute::make('publishedAt')
            ->type(DateTime::make())
            ->writable()]));

        $this->update('posts', '1', ['publishedAt' => '2024-01-01T00:00:00+00:00']);

        $this->assertSame(
            '2024-01-01 10:30:00',
            $this->db->getConnection()->table('posts')->where('id', 1)->value('published_at'),
        );
    }

    public function test_offset_pagination()
    {
        foreach (range(1, 5) as $i) {
            User::create(['name' => "User $i"]);
        }

        $this->api->resource($this->users(endpoints: [Index::make()->paginate(2)]));

        $first = $this->get('/users');
        $this->assertSame(['1', '2'], $this->ids($first));
        $this->assertSame(5, $this->document($first)['meta']['page']['total']);
        $this->assertArrayHasKey('next', $this->document($first)['links']);

        $last = $this->get('/users?page[offset]=4');
        $this->assertSame(['5'], $this->ids($last));
        $this->assertArrayNotHasKey('next', $this->document($last)['links']);
    }

    public function test_offset_pagination_counts_with_scope_applied()
    {
        foreach (range(1, 5) as $i) {
            User::create(['name' => "User $i", 'is_admin' => ($i % 2) === 0]);
        }

        $this->api->resource($this->users(endpoints: [Index::make()->paginate(2)], scope: fn($query) => $query->where(
            'is_admin',
            true,
        )));

        $response = $this->get('/users');

        $this->assertSame(['2', '4'], $this->ids($response));
        $this->assertSame(2, $this->document($response)['meta']['page']['total']);
    }

    public function test_cursor_pagination()
    {
        foreach (range(1, 5) as $i) {
            User::create(['name' => "User $i"]);
        }

        $this->api->resource($this->users(endpoints: [Index::make()->cursorPaginate(2)], defaultSort: 'id'));

        $first = $this->document($this->get('/users'));
        $this->assertSame(['1', '2'], array_column($first['data'], 'id'));
        $this->assertNull($first['links']['prev'] ?? null);

        $cursor = $first['data'][1]['meta']['page']['cursor'];

        $second = $this->document($this->get('/users?page[after]=' . urlencode($cursor)));
        $this->assertSame(['3', '4'], array_column($second['data'], 'id'));

        $before = $second['data'][0]['meta']['page']['cursor'];

        $previous = $this->document($this->get('/users?page[before]=' . urlencode($before)));
        $this->assertSame(['1', '2'], array_column($previous['data'], 'id'));
    }

    public static function invalidCursors(): array
    {
        return [
            'not JSON' => ['nonsense'],
            'JSON scalar' => [base64_encode('1')],
            'JSON without cursor parameters' => [base64_encode('{}')],
        ];
    }

    #[DataProvider('invalidCursors')]
    public function test_cursor_pagination_rejects_invalid_cursors(string $cursor)
    {
        $this->api->resource($this->users(endpoints: [Index::make()->cursorPaginate(2)], defaultSort: 'id'));

        // Laravel converts warnings, such as for missing cursor parameters, to
        // exceptions.
        set_error_handler(fn($level, $message) => throw new ErrorException($message, 0, $level));

        try {
            $e = $this->exception(fn() => $this->get('/users?page[before]=' . urlencode($cursor)));
        } finally {
            restore_error_handler();
        }

        $this->assertInstanceOf(InvalidPageCursorException::class, $e);
        $this->assertSame('page[before]', $e->getJsonApiError()['source']['parameter']);
    }

    public function test_cursor_pagination_rejects_ranges()
    {
        $this->api->resource($this->users(endpoints: [Index::make()->cursorPaginate(2)]));

        $this->expectException(RangePaginationNotSupportedException::class);

        $this->get('/users?page[after]=a&page[before]=b');
    }

    private function users(
        array $endpoints = [],
        array $fields = [],
        ?Closure $scope = null,
        ?string $defaultSort = null,
    ): TestResource {
        return new TestResource(
            'users',
            User::class,
            endpoints: $endpoints
            ?: [
                Index::make(),
                Show::make(),
                Create::make(),
                Update::make(),
                Delete::make(),
            ],
            fields: $fields ?: [Attribute::make('name')],
            sorts: [SortColumn::make('id')],
            defaultSort: $defaultSort,
            scope: $scope,
        );
    }

    private function posts(array $fields = []): TestResource
    {
        return new TestResource(
            'posts',
            Post::class,
            endpoints: [Show::make(), Update::make(), Delete::make()],
            fields: $fields ?: [Attribute::make('title')],
        );
    }

    private function hookedUsers(): TestResource
    {
        return new class(
            'users',
            User::class,
            endpoints: [Create::make(), Update::make()],
            fields: [Attribute::make('name')->writable()],
        ) extends TestResource {
            public array $calls = [];

            public function creating(User $user): void
            {
                $this->calls[] = 'creating';
            }

            public function updating(User $user): void
            {
                $this->calls[] = 'updating';
            }

            public function saving(User $user): User
            {
                $this->calls[] = 'saving';

                $replacement = clone $user;
                $replacement->name .= ' (saving)';

                return $replacement;
            }

            public function saved(User $user): void
            {
                $this->calls[] = 'saved';
            }

            public function created(User $user): void
            {
                $this->calls[] = 'created';
            }

            public function updated(User $user): void
            {
                $this->calls[] = 'updated';
            }
        };
    }
}
