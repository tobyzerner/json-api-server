<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Laravel\Field\ToMany;
use Tobyz\JsonApiServer\Laravel\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\laravel\Models\Comment;
use Tobyz\Tests\JsonApiServer\laravel\Models\Country;
use Tobyz\Tests\JsonApiServer\laravel\Models\Image;
use Tobyz\Tests\JsonApiServer\laravel\Models\Post;
use Tobyz\Tests\JsonApiServer\laravel\Models\Tag;
use Tobyz\Tests\JsonApiServer\laravel\Models\User;

class EloquentRelationshipTest extends LaravelTestCase
{
    public function test_includes_belongs_to_in_one_query()
    {
        $toby = User::create(['name' => 'Toby']);
        $franz = User::create(['name' => 'Franz']);
        Post::create(['title' => 'A', 'user_id' => $toby->id]);
        Post::create(['title' => 'B', 'user_id' => $franz->id]);
        Post::create(['title' => 'C', 'user_id' => $toby->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')->type('users')->includable()]]);

        [$response, $queries] = $this->queries(fn() => $this->get('/posts?include=author'));

        $this->assertSame(['1', '2', '1'], array_column($this->linkage($response, 'author'), 'id'));
        $this->assertEqualsCanonicalizing(
            ['1', '2'],
            array_column($this->document($response)['included'], 'id'),
        );
        $this->assertQueryCount(1, 'users', $queries);
    }

    public function test_included_belongs_to_applies_related_resource_scope()
    {
        $admin = User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A', 'user_id' => $admin->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->includable()]], scopes: [
            'users' => fn($query) => $query->where('is_admin', false),
        ]);

        $document = $this->document($this->get('/posts/1?include=author'));

        $this->assertNull($document['data']['relationships']['author']['data']);
        $this->assertArrayNotHasKey('included', $document);
    }

    public function test_included_belongs_to_applies_field_scope()
    {
        $admin = User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A', 'user_id' => $admin->id]);

        $scopeContext = null;

        $this->resources(fields: ['posts' => [
            ToOne::make('author')
                ->type('users')
                ->includable()
                ->scope(function ($query, Context $context) use (&$scopeContext) {
                    $scopeContext = $context;
                    $query->where('is_admin', false);
                }),
        ]]);

        $document = $this->document($this->get('/posts/1?include=author'));

        $this->assertNull($document['data']['relationships']['author']['data']);
        $this->assertInstanceOf(Context::class, $scopeContext);
    }

    public function test_belongs_to_without_foreign_key_does_not_query()
    {
        Post::create(['title' => 'A']);

        $this->resources(fields: ['posts' => [ToOne::make('author')->type('users')->includable()]]);

        [$response, $queries] = $this->queries(fn() => $this->get('/posts/1?include=author'));

        $this->assertNull($this->document($response)['data']['relationships']['author']['data']);
        $this->assertQueryCount(0, 'users', $queries);
    }

    public function test_to_one_has_no_linkage_by_default()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')->type('users')]]);

        [$response, $queries] = $this->queries(fn() => $this->get('/posts/1'));

        $this->assertArrayNotHasKey(
            'data',
            $this->document($response)['data']['relationships']['author'] ?? [],
        );
        $this->assertQueryCount(0, 'users', $queries);
    }

    public function test_linkage_loads_related_models_through_scopes_in_linkage_only_context()
    {
        $toby = User::create(['name' => 'Toby']);
        $admin = User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A', 'user_id' => $toby->id]);
        Post::create(['title' => 'B', 'user_id' => $admin->id]);

        $linkageOnly = [];

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->withLinkage()]], scopes: ['users' => function ($query, Context $context) use (
            &$linkageOnly,
        ) {
            $linkageOnly[] = $context->linkageOnly;
            $query->where('is_admin', false);
        }]);

        [$response, $queries] = $this->queries(fn() => $this->get('/posts'));

        $document = $this->document($response);

        $this->assertSame(
            ['type' => 'users', 'id' => '1'],
            $document['data'][0]['relationships']['author']['data'],
        );
        $this->assertNull($document['data'][1]['relationships']['author']['data']);
        $this->assertSame([true], $linkageOnly);
        $this->assertQueryCount(1, 'users', $queries);
    }

    public function test_linkage_only_relations_are_not_set_on_models()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $posts = [];

        $this->resources(fields: ['posts' => [
            ToOne::make('author')->type('users')->withLinkage(),
            Attribute::make('title')->get(function (Post $post) use (&$posts) {
                $posts[] = $post;

                return $post->title;
            }),
        ]], scopes: [
            // Load only IDs when only linkage is needed.
            'users' => fn($query, Context $context) => $context->linkageOnly
                ? $query->select('users.id')
                : null,
        ]);

        $document = $this->document($this->get('/posts'));

        $this->assertSame('1', $document['data'][0]['relationships']['author']['data']['id']);
        $this->assertFalse($posts[0]->relationLoaded('author'));
    }

    public function test_linkage_only_and_included_relations_are_loaded_separately()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);
        Comment::create(['body' => 'Hi', 'post_id' => 1, 'user_id' => $user->id]);

        $this->resources(fields: [
            'posts' => [ToOne::make('author')->type('users')->withLinkage()->includable()],
            'comments' => [
                ToOne::make('post')->type('posts')->includable(),
                ToOne::make('user')->type('users')->withLinkage()->includable(),
            ],
        ], scopes: [
            'users' => fn($query, Context $context) => $context->linkageOnly
                ? $query->select('users.id')
                : null,
        ]);

        // The comment's user is linkage-only, while the post's author is
        // included, so the author must be loaded in full.
        $document = $this->document($this->get('/comments/1?include=post.author'));

        $users = array_filter(
            $document['included'],
            fn($resource) => $resource['type'] === 'users',
        );

        $this->assertSame(['Toby'], array_values($this->attributeValues($users, 'name')));
        $this->assertSame('1', $document['data']['relationships']['user']['data']['id']);
    }

    public function test_includes_nested_relationships()
    {
        $country = Country::create(['name' => 'Australia']);
        $user = User::create(['name' => 'Toby', 'country_id' => $country->id]);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $this->resources(fields: [
            'posts' => [ToOne::make('author')->type('users')->includable()],
            'users' => [
                Attribute::make('name'),
                ToOne::make('country')->type('countries')->includable(),
            ],
        ]);

        $document = $this->document($this->get('/posts/1?include=author.country'));

        $this->assertEqualsCanonicalizing(
            ['users', 'countries'],
            array_column($document['included'], 'type'),
        );
    }

    public function test_relationship_property_maps_to_relation_method()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $this->resources(fields: ['posts' => [ToOne::make('writer')
            ->type('users')
            ->property('author')
            ->includable()]]);

        $document = $this->document($this->get('/posts/1?include=writer'));

        $this->assertSame('1', $document['data']['relationships']['writer']['data']['id']);
    }

    public function test_includes_has_many_with_related_scope()
    {
        Post::create(['title' => 'A']);
        Comment::create(['body' => 'Visible', 'post_id' => 1]);
        Comment::create(['body' => 'Hidden', 'post_id' => 1]);

        $this->resources(fields: ['posts' => [ToMany::make('comments')
            ->type('comments')
            ->includable()]], scopes: [
            'comments' => fn($query) => $query->where('body', '!=', 'Hidden'),
        ]);

        $document = $this->document($this->get('/posts/1?include=comments'));

        $this->assertSame(
            [['type' => 'comments', 'id' => '1']],
            $document['data']['relationships']['comments']['data'],
        );
    }

    public function test_includes_belongs_to_many_with_field_scope()
    {
        $post = Post::create(['title' => 'A']);
        $post->tags()->attach([
            Tag::create(['name' => 'php'])->id,
            Tag::create(['name' => 'laravel'])->id,
        ]);

        $this->resources(fields: ['posts' => [
            ToMany::make('tags')
                ->type('tags')
                ->includable()
                ->scope(fn($query) => $query->where('name', 'php')),
        ]]);

        $document = $this->document($this->get('/posts/1?include=tags'));

        $this->assertSame(
            [['type' => 'tags', 'id' => '1']],
            $document['data']['relationships']['tags']['data'],
        );
    }

    public function test_to_many_linkage()
    {
        Post::create(['title' => 'A']);
        Comment::create(['body' => 'One', 'post_id' => 1]);
        Comment::create(['body' => 'Two', 'post_id' => 1]);

        $this->resources(fields: ['posts' => [ToMany::make('comments')
            ->type('comments')
            ->withLinkage()]]);

        $document = $this->document($this->get('/posts/1'));

        $this->assertSame(
            ['1', '2'],
            array_column($document['data']['relationships']['comments']['data'], 'id'),
        );
    }

    public function test_related_endpoint_lists_with_scopes_and_pagination()
    {
        Post::create(['title' => 'A']);
        Post::create(['title' => 'B']);

        foreach (['One', 'Two', 'Three', 'Hidden'] as $body) {
            Comment::create(['body' => $body, 'post_id' => 1]);
        }

        Comment::create(['body' => 'Other post', 'post_id' => 2]);

        $this->resources(fields: ['posts' => [
            ToMany::make('comments')
                ->type('comments')
                ->scope(fn($query) => $query->where('body', '!=', 'Three')),
        ]], scopes: ['comments' => fn($query) => $query->where('body', '!=', 'Hidden')]);

        $response = $this->get('/posts/1/comments');

        $this->assertSame(['1', '2'], $this->ids($response));
    }

    public function test_to_one_relationship_endpoint()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->withLinkage()]]);

        $document = $this->document($this->get('/posts/1/relationships/author'));

        $this->assertSame(['type' => 'users', 'id' => '1'], $document['data']);
    }

    public function test_to_one_related_endpoint()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $this->resources(fields: ['posts' => [ToOne::make('author')->type('users')->includable()]]);

        $document = $this->document($this->get('/posts/1/author'));

        $this->assertSame('Toby', $document['data']['attributes']['name']);
    }

    public function test_creates_with_belongs_to()
    {
        User::create(['name' => 'Toby']);

        $this->resources(fields: ['posts' => [
            Attribute::make('title')->writable(),
            ToOne::make('author')->type('users')->writable(),
        ]]);

        $response = $this->create(
            'posts',
            ['title' => 'A'],
            ['author' => ['data' => ['type' => 'users', 'id' => '1']]],
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(1, Post::find(1)->user_id);
    }

    public function test_updates_belongs_to()
    {
        User::create(['name' => 'Toby']);
        User::create(['name' => 'Franz']);
        Post::create(['title' => 'A', 'user_id' => 1]);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->writable()
            ->nullable()]]);

        $this->update('posts', '1', relationships: [
            'author' => ['data' => ['type' => 'users', 'id' => '2']],
        ]);

        $this->assertSame(2, Post::find(1)->user_id);

        $this->update('posts', '1', relationships: ['author' => ['data' => null]]);

        $this->assertNull(Post::find(1)->user_id);
    }

    public function test_writing_a_relationship_resolves_related_resources_through_scope()
    {
        User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A']);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->writable()]], scopes: [
            'users' => fn($query) => $query->where('is_admin', false),
        ]);

        $this->exception(fn() => $this->update('posts', '1', relationships: [
            'author' => ['data' => ['type' => 'users', 'id' => '1']],
        ]));

        $this->assertNull(Post::find(1)->user_id);
    }

    public function test_updates_to_one_relationship_endpoint()
    {
        User::create(['name' => 'Toby']);
        Post::create(['title' => 'A']);

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->writable()
            ->withLinkage()]]);

        $response = $this->send('PATCH', '/posts/1/relationships/author', [
            'data' => ['type' => 'users', 'id' => '1'],
        ]);

        $this->assertSame(['type' => 'users', 'id' => '1'], $this->document($response)['data']);
        $this->assertSame(1, Post::find(1)->user_id);
    }

    public function test_replaces_belongs_to_many()
    {
        $post = Post::create(['title' => 'A']);
        $php = Tag::create(['name' => 'php']);
        $laravel = Tag::create(['name' => 'laravel']);
        $post->tags()->attach($php);

        $this->resources(fields: ['posts' => [ToMany::make('tags')->type('tags')->writable()]]);

        $this->update('posts', '1', relationships: [
            'tags' => ['data' => [['type' => 'tags', 'id' => (string) $laravel->id]]],
        ]);

        $this->assertSame([$laravel->id], $post->tags()->pluck('tags.id')->all());
    }

    public function test_resolves_to_many_identifiers_in_one_query()
    {
        $post = Post::create(['title' => 'A']);
        Tag::create(['name' => 'php']);
        Tag::create(['name' => 'laravel']);
        Tag::create(['name' => 'eloquent']);

        $this->resources(fields: ['posts' => [ToMany::make('tags')->type('tags')->writable()]]);

        [, $queries] = $this->queries(fn() => $this->update('posts', '1', relationships: [
            'tags' => [
                'data' => [
                    ['type' => 'tags', 'id' => '3'],
                    ['type' => 'tags', 'id' => '1'],
                    ['type' => 'tags', 'id' => '3'],
                ],
            ],
        ]));

        $this->assertQueryCount(1, 'tags', $queries);
        $this->assertEqualsCanonicalizing([1, 3], $post->tags()->pluck('tags.id')->all());
    }

    public function test_to_many_identifier_outside_scope_is_not_found()
    {
        Post::create(['title' => 'A']);
        Tag::create(['name' => 'php']);
        Tag::create(['name' => 'hidden']);

        $this->resources(fields: ['posts' => [ToMany::make('tags')
            ->type('tags')
            ->writable()]], scopes: [
            'tags' => fn($query) => $query->where('name', '!=', 'hidden'),
        ]);

        $e = $this->exception(fn() => $this->update('posts', '1', relationships: [
            'tags' => [
                'data' => [['type' => 'tags', 'id' => '1'], ['type' => 'tags', 'id' => '2']],
            ],
        ]));

        $this->assertInstanceOf(ResourceNotFoundException::class, $e);
        $this->assertSame(
            '/data/relationships/tags/data/1',
            $e->getJsonApiError()['source']['pointer'],
        );
    }

    public function test_attaches_and_detaches_belongs_to_many()
    {
        $post = Post::create(['title' => 'A']);
        $php = Tag::create(['name' => 'php']);
        $laravel = Tag::create(['name' => 'laravel']);
        $post->tags()->attach($php);

        $this->resources(fields: ['posts' => [ToMany::make('tags')
            ->type('tags')
            ->writable()
            ->attachable()
            ->withLinkage()]]);

        $this->send('POST', '/posts/1/relationships/tags', [
            'data' => [
                ['type' => 'tags', 'id' => (string) $laravel->id],
                ['type' => 'tags', 'id' => (string) $php->id],
            ],
        ]);

        $this->assertEqualsCanonicalizing(
            [$php->id, $laravel->id],
            $post->tags()->pluck('tags.id')->all(),
        );

        $this->send('DELETE', '/posts/1/relationships/tags', [
            'data' => [['type' => 'tags', 'id' => (string) $php->id]],
        ]);

        $this->assertSame([$laravel->id], $post->tags()->pluck('tags.id')->all());
    }

    public function test_includes_morph_to_with_each_types_scope()
    {
        User::create(['name' => 'Toby']);
        User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A']);

        Image::create(['url' => 'a', 'imageable_type' => User::class, 'imageable_id' => 1]);
        Image::create(['url' => 'b', 'imageable_type' => User::class, 'imageable_id' => 2]);
        Image::create(['url' => 'c', 'imageable_type' => Post::class, 'imageable_id' => 1]);
        Image::create(['url' => 'd']);

        $this->resources(fields: ['images' => [ToOne::make('imageable')
            ->type(['users', 'posts'])
            ->includable()]], scopes: ['users' => fn($query) => $query->where('is_admin', false)]);

        $this->assertSame(
            [['type' => 'users', 'id' => '1'], null, ['type' => 'posts', 'id' => '1'], null],
            $this->linkage($this->get('/images?include=imageable'), 'imageable'),
        );
    }

    public function test_related_resource_scope_sees_to_many_relationship()
    {
        Post::create(['title' => 'A']);
        Comment::create(['body' => 'One', 'post_id' => 1]);

        $comments = ToMany::make('comments')->type('comments')->includable()->withLinkage();
        $fields = [];

        $this->resources(fields: ['posts' => [$comments]], scopes: [
            'comments' => $this->recordField($fields),
        ]);

        $this->get('/posts/1/comments');
        $this->get('/posts/1?include=comments');
        $this->get('/posts/1/relationships/comments');

        $this->assertSame([$comments, $comments, $comments], $fields);
    }

    public function test_related_resource_scope_sees_to_one_relationship()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $author = ToOne::make('author')->type('users')->includable()->withLinkage();
        $fields = [];

        $this->resources(fields: ['posts' => [$author]], scopes: [
            'users' => $this->recordField($fields),
        ]);

        $this->get('/posts/1/author');
        $this->get('/posts/1?include=author');
        $this->get('/posts/1/relationships/author');

        $this->assertSame([$author, $author, $author], $fields);
    }

    public function test_to_one_relationship_endpoint_scopes_related_resource_for_linkage_only()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $linkageOnly = [];

        $this->resources(fields: ['posts' => [ToOne::make('author')
            ->type('users')
            ->includable()]], scopes: [
            'users' => function ($query, Context $context) use (&$linkageOnly) {
                $linkageOnly[] = $context->linkageOnly;
            },
        ]);

        $this->get('/posts/1/relationships/author');

        $this->assertSame([true], $linkageOnly);
    }

    public function test_resource_scope_sees_no_relationship_on_its_own_endpoints()
    {
        Comment::create(['body' => 'One']);

        $fields = [];

        $this->resources(scopes: [
            'comments' => $this->recordField($fields),
        ]);

        $this->get('/comments');
        $this->get('/comments/1');

        $this->assertSame([null, null], $fields);
    }

    public function test_morph_to_target_scopes_see_the_relationship()
    {
        User::create(['name' => 'Toby']);
        Post::create(['title' => 'A']);

        Image::create(['url' => 'a', 'imageable_type' => User::class, 'imageable_id' => 1]);
        Image::create(['url' => 'b', 'imageable_type' => Post::class, 'imageable_id' => 1]);

        $imageable = ToOne::make('imageable')->type(['users', 'posts'])->includable();
        $fields = [];

        $this->resources(fields: ['images' => [$imageable]], scopes: [
            'users' => $this->recordField($fields),
            'posts' => $this->recordField($fields),
        ]);

        $this->get('/images?include=imageable');

        $this->assertSame([$imageable, $imageable], $fields);
    }

    public function test_resolving_linkage_does_not_apply_list_scope()
    {
        User::create(['name' => 'A', 'is_admin' => true]);
        User::create(['name' => 'B', 'is_admin' => true]);
        $post = Post::create(['title' => 'A']);
        Tag::create(['name' => 'unlisted']);
        Tag::create(['name' => 'unlisted']);

        $this->resources(
            fields: ['posts' => [
                ToOne::make('author')->type('users')->writable(),
                ToMany::make('tags')->type('tags')->writable()->attachable(),
            ]],
            listScopes: [
                'users' => fn($query) => $query->where('is_admin', false),
                'tags' => fn($query) => $query->where('name', '!=', 'unlisted'),
            ],
        );

        $this->update('posts', '1', relationships: [
            'author' => ['data' => ['type' => 'users', 'id' => '1']],
            'tags' => ['data' => [['type' => 'tags', 'id' => '1']]],
        ]);

        $this->assertSame(1, Post::find(1)->user_id);
        $this->assertSame([1], $post->tags()->pluck('tags.id')->all());

        $this->send('PATCH', '/posts/1/relationships/author', [
            'data' => ['type' => 'users', 'id' => '2'],
        ]);
        $this->send('POST', '/posts/1/relationships/tags', [
            'data' => [['type' => 'tags', 'id' => '2']],
        ]);

        $this->assertSame(2, Post::find(1)->user_id);
        $this->assertEqualsCanonicalizing([1, 2], $post->tags()->pluck('tags.id')->all());
    }

    public function test_to_one_related_model_does_not_apply_list_scope()
    {
        $admin = User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A', 'user_id' => $admin->id]);

        $this->resources(
            fields: ['posts' => [ToOne::make('author')
                ->type('users')
                ->includable()
                ->withLinkage()]],
            listScopes: ['users' => fn($query) => $query->where('is_admin', false)],
        );

        $author = ['type' => 'users', 'id' => '1'];
        $data = fn($uri) => $this->document($this->get($uri))['data'];

        $this->assertSame($author, $data('/posts/1')['relationships']['author']['data']);
        $this->assertSame(
            $author,
            $data('/posts/1?include=author')['relationships']['author']['data'],
        );
        $this->assertSame('1', $data('/posts/1/author')['id']);
        $this->assertSame($author, $data('/posts/1/relationships/author'));
    }

    public function test_list_scope_applies_only_to_lists()
    {
        Post::create(['title' => 'A']);
        Comment::create(['body' => 'Unlisted', 'post_id' => 1]);
        Comment::create(['body' => 'Listed', 'post_id' => 1]);

        $this->resources(
            fields: ['posts' => [ToMany::make('comments')
                ->type('comments')
                ->includable()
                ->withLinkage()]],
            listScopes: ['comments' => fn($query) => $query->where('body', '!=', 'Unlisted')],
        );

        $data = fn($uri) => $this->document($this->get($uri))['data'];

        $this->assertSame(['2'], $this->ids($this->get('/comments')));
        $this->assertSame('1', $data('/comments/1')['id']);
        $this->assertSame(['2'], $this->ids($this->get('/posts/1/comments')));
        $linkage = fn($uri) => array_column(
            $data($uri)['relationships']['comments']['data'],
            'id',
        );

        $this->assertSame(['2'], $linkage('/posts/1'));
        $this->assertSame(['2'], $linkage('/posts/1?include=comments'));
        $this->assertSame(['2'], array_column($data('/posts/1/relationships/comments'), 'id'));
    }

    /**
     * Build a scope that records the field on its context.
     */
    private function recordField(array &$fields): Closure
    {
        return function ($query, Context $context) use (&$fields) {
            $fields[] = $context->field;
        };
    }
}
