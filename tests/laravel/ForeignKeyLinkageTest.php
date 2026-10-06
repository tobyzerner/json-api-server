<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Id;
use Tobyz\Tests\JsonApiServer\laravel\Models\Image;
use Tobyz\Tests\JsonApiServer\laravel\Models\Post;
use Tobyz\Tests\JsonApiServer\laravel\Models\User;

class ForeignKeyLinkageTest extends LaravelTestCase
{
    public function test_builds_linkage_from_foreign_key_without_querying()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);
        Post::create(['title' => 'B']);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->withForeignKeyLinkage()]]);

        [$response, $queries] = $this->queries(fn() => $this->get('/posts'));

        $this->assertSame(
            [['type' => 'users', 'id' => '1'], null],
            $this->linkage($response, 'author'),
        );
        $this->assertQueryCount(0, 'users', $queries);
    }

    public function test_skips_related_resource_scope()
    {
        $admin = User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A', 'user_id' => $admin->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->withForeignKeyLinkage()]], scopes: [
            'users' => fn($query) => $query->where('is_admin', false),
        ]);

        $this->assertSame(
            [['type' => 'users', 'id' => '1']],
            $this->linkage($this->get('/posts'), 'author'),
        );
    }

    public function test_included_relationships_are_loaded_through_scopes()
    {
        $admin = User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A', 'user_id' => $admin->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->withForeignKeyLinkage()
            ->includable()]], scopes: ['users' => fn($query) => $query->where('is_admin', false)]);

        $this->assertSame([null], $this->linkage($this->get('/posts?include=author'), 'author'));
    }

    public function test_condition_controls_whether_linkage_is_included()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $show = false;

        $this->resources(fields: ['posts' => [
            ToOne::make('author')
                ->type('users')
                ->withForeignKeyLinkage(function () use (&$show) {
                    return $show;
                }),
        ]]);

        [$response, $queries] = $this->queries(fn() => $this->get('/posts'));

        $this->assertArrayNotHasKey(
            'data',
            $this->document($response)['data'][0]['relationships']['author'] ?? [],
        );
        $this->assertQueryCount(0, 'users', $queries);

        $show = true;

        $this->assertSame(
            [['type' => 'users', 'id' => '1']],
            $this->linkage($this->get('/posts'), 'author'),
        );
    }

    public function test_with_linkage_changes_only_the_condition()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $this->resources(fields: ['posts' => [
            ToOne::make('author')->type('users')->withForeignKeyLinkage(false)->withLinkage(),
        ]], scopes: ['users' => fn($query) => $query->where('is_admin', true)]);

        // withForeignKeyLinkage(false) doesn't build linkage from the foreign
        // key, so the later withLinkage() loads it through the scope.
        $this->assertSame([null], $this->linkage($this->get('/posts'), 'author'));
    }

    public function test_closure_condition_marks_field_for_foreign_key_linkage()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $this->resources(fields: ['posts' => [
            ToOne::make('author')
                ->type('users')
                ->withForeignKeyLinkage(fn() => false)
                ->withLinkage(),
        ]], scopes: ['users' => fn($query) => $query->where('is_admin', true)]);

        // A false condition still marks the field for foreign key linkage, so
        // a later withLinkage() builds it from the foreign key.
        $this->assertSame(
            [['type' => 'users', 'id' => '1']],
            $this->linkage($this->get('/posts'), 'author'),
        );
    }

    public function test_relationship_endpoint_uses_foreign_key()
    {
        $admin = User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A', 'user_id' => $admin->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->withForeignKeyLinkage()]], scopes: [
            'users' => fn($query) => $query->where('is_admin', false),
        ]);

        [$response, $queries] = $this->queries(fn() => $this->get('/posts/1/relationships/author'));

        $this->assertSame(['type' => 'users', 'id' => '1'], $this->document($response)['data']);
        $this->assertQueryCount(0, 'users', $queries);
    }

    public function test_morph_to()
    {
        $user = User::create(['name' => 'Toby']);
        $post = Post::create(['title' => 'A']);

        Image::create(['url' => 'a', 'imageable_type' => User::class, 'imageable_id' => $user->id]);
        Image::create(['url' => 'b', 'imageable_type' => Post::class, 'imageable_id' => $post->id]);
        Image::create(['url' => 'c', 'imageable_id' => $post->id]);
        Image::create(['url' => 'd']);

        $this->resources(fields: ['images' => [ToOne::make('imageable')
            ->type(['users', 'posts'])
            ->withForeignKeyLinkage()]]);

        [$response, $queries] = $this->queries(fn() => $this->get('/images'));

        $this->assertSame(
            [['type' => 'users', 'id' => '1'], ['type' => 'posts', 'id' => '1'], null, null],
            $this->linkage($response, 'imageable'),
        );
        $this->assertQueryCount(0, 'users', $queries);
        $this->assertQueryCount(0, 'posts', $queries);
    }

    public function test_falls_back_when_owner_key_is_not_the_id_column()
    {
        User::create(['name' => 'Franz', 'email' => 'franz@example.com']);
        $user = User::create(['name' => 'Toby', 'email' => 'toby@example.com']);
        Post::create(['title' => 'A', 'author_email' => $user->email]);

        $this->resources(fields: ['posts' => [
            ToOne::make('authorByEmail')->type('users')->withForeignKeyLinkage(),
        ]]);

        $this->assertSame(
            [['type' => 'users', 'id' => '2']],
            $this->linkage($this->get('/posts'), 'authorByEmail'),
        );
    }

    public function test_morph_to_loads_only_types_whose_id_is_not_the_key()
    {
        $user = User::create(['name' => 'Toby', 'uuid' => 'uuid-toby']);
        $post = Post::create(['title' => 'A']);

        Image::create(['url' => 'a', 'imageable_type' => User::class, 'imageable_id' => $user->id]);
        Image::create(['url' => 'b', 'imageable_type' => Post::class, 'imageable_id' => $post->id]);

        $this->resources(
            fields: ['images' => [ToOne::make('imageable')
                ->type(['users', 'posts'])
                ->withForeignKeyLinkage()]],
            ids: ['users' => Id::make()->property('uuid')],
        );

        [$response, $queries] = $this->queries(fn() => $this->get('/images'));

        $this->assertSame(
            [['type' => 'users', 'id' => 'uuid-toby'], ['type' => 'posts', 'id' => '1']],
            $this->linkage($response, 'imageable'),
        );
        $this->assertQueryCount(1, 'users', $queries);
        $this->assertQueryCount(0, 'posts', $queries);
    }

    public function test_uses_foreign_key_when_it_references_the_id_attribute()
    {
        $user = User::create(['name' => 'Toby', 'email' => 'toby@example.com']);
        Post::create(['title' => 'A', 'author_email' => $user->email]);

        $this->resources(
            fields: ['posts' => [ToOne::make('authorByEmail')
                ->type('users')
                ->withForeignKeyLinkage()]],
            ids: ['users' => Id::make()->property('email')],
        );

        [$response, $queries] = $this->queries(fn() => $this->get('/posts'));

        $this->assertSame(
            [['type' => 'users', 'id' => 'toby@example.com']],
            $this->linkage($response, 'authorByEmail'),
        );
        $this->assertQueryCount(0, 'users', $queries);
    }

    public function test_resolves_related_resource_from_model_with_foreign_key_set()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->withForeignKeyLinkage()]]);

        // A collection may need the model's attributes to tell its type.
        $this->usersResolvableOnlyWithKey();

        [$response, $queries] = $this->queries(fn() => $this->get('/posts'));

        $this->assertSame([['type' => 'users', 'id' => '1']], $this->linkage($response, 'author'));
        $this->assertQueryCount(0, 'users', $queries);
    }

    public function test_loads_normally_when_related_resource_cannot_be_resolved()
    {
        User::create(['name' => 'Toby', 'email' => 'toby@example.com']);
        Post::create(['title' => 'A', 'author_email' => 'toby@example.com']);

        $this->resources(
            fields: ['posts' => [ToOne::make('authorByEmail')
                ->type('users')
                ->withForeignKeyLinkage()]],
            ids: ['users' => Id::make()->property('email')],
        );

        // Without its key, the related model can't be resolved to a resource.
        $this->usersResolvableOnlyWithKey(Id::make()->property('email'));

        [$response, $queries] = $this->queries(fn() => $this->get('/posts'));

        $this->assertSame(
            [['type' => 'users', 'id' => 'toby@example.com']],
            $this->linkage($response, 'authorByEmail'),
        );
        $this->assertQueryCount(1, 'users', $queries);
    }

    public function test_loaded_relation_is_used()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->withForeignKeyLinkage()]], scopes: [
            'posts' => fn($query) => $query->with([
                'author' => fn($query) => $query->where('is_admin', true),
            ]),
        ]);

        // The eager-loaded relation (constrained to admins) wins over the
        // foreign key.
        $this->assertSame([null], $this->linkage($this->get('/posts'), 'author'));
    }

    public function test_non_belongs_to_relations_load_through_scope()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'Hidden', 'user_id' => $user->id]);

        $this->resources(fields: ['users' => [ToOne::make('latestPost')
            ->type('posts')
            ->withForeignKeyLinkage()]], scopes: [
            'posts' => fn($query) => $query->where('title', '!=', 'Hidden'),
        ]);

        $this->assertSame([null], $this->linkage($this->get('/users'), 'latestPost'));
    }

    public function test_custom_getter_takes_precedence()
    {
        Post::create(['title' => 'A', 'user_id' => 1]);

        $this->resources(fields: ['posts' => [
            ToOne::make('author')
                ->type('users')
                ->withForeignKeyLinkage()
                ->get(fn() => null),
        ]]);

        $this->assertSame([null], $this->linkage($this->get('/posts'), 'author'));
    }

    /**
     * Replace the users resource with one that can only tell its type from a
     * model with its key set.
     */
    private function usersResolvableOnlyWithKey(?Id $id = null): void
    {
        $this->api->resource(
            new class('users', User::class, id: $id) extends TestResource {
                public function resource(object $model, Context $context): ?string
                {
                    return $model->getKey() ? parent::resource($model, $context) : null;
                }
            },
        );
    }
}
