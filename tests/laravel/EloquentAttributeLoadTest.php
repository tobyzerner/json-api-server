<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Illuminate\Database\Eloquent\Collection;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Laravel\Field\Attribute;
use Tobyz\JsonApiServer\Laravel\Field\ToOne;
use Tobyz\JsonApiServer\Serializer;
use Tobyz\Tests\JsonApiServer\laravel\Models\Country;
use Tobyz\Tests\JsonApiServer\laravel\Models\Post;
use Tobyz\Tests\JsonApiServer\laravel\Models\User;

class EloquentAttributeLoadTest extends LaravelTestCase
{
    public function test_loads_relations_in_one_query_for_visible_models()
    {
        $australia = Country::create(['name' => 'Australia']);
        $austria = Country::create(['name' => 'Austria']);

        User::create(['name' => 'Toby', 'country_id' => $australia->id]);
        User::create(['name' => 'Hidden', 'country_id' => $austria->id]);
        User::create(['name' => 'Franz', 'country_id' => $austria->id]);

        $this->users([
            Attribute::make('countryName')
                ->visible(fn(User $user) => $user->name !== 'Hidden')
                ->load('country')
                ->get(fn(User $user) => $user->country->name),
        ]);

        [$response, $queries] = $this->queries(fn() => $this->get('/users'));

        $this->assertSame(
            ['Australia', null, 'Austria'],
            $this->attributeValues($this->document($response)['data'], 'countryName'),
        );
        $this->assertQueryCount(1, 'countries', $queries);
    }

    public function test_loads_with_constraints()
    {
        $user = User::create(['name' => 'Toby']);
        Post::create(['title' => 'B', 'user_id' => $user->id]);
        Post::create(['title' => 'A', 'user_id' => $user->id]);

        $constraintContext = null;

        $this->users([
            Attribute::make('postTitles')->load([
                'posts' => function ($query, Context $context) use (&$constraintContext) {
                    $constraintContext = $context;
                    $query->orderBy('title');
                },
            ])->get(fn(User $user) => $user->posts->pluck('title')->all()),
        ]);

        $document = $this->document($this->get('/users'));

        $this->assertSame(['A', 'B'], $document['data'][0]['attributes']['postTitles']);
        $this->assertInstanceOf(Context::class, $constraintContext);
    }

    public function test_does_not_load_when_field_is_not_requested()
    {
        User::create([
            'name' => 'Toby',
            'country_id' => Country::create(['name' => 'Australia'])->id,
        ]);

        $this->users([
            Attribute::make('name'),
            Attribute::make('countryName')->load(
                'country',
            )->get(fn(User $user) => $user->country->name),
        ]);

        [, $queries] = $this->queries(fn() => $this->get('/users?fields[users]=name'));

        $this->assertQueryCount(0, 'countries', $queries);
    }

    public function test_does_not_load_when_no_model_is_visible()
    {
        User::create([
            'name' => 'Toby',
            'country_id' => Country::create(['name' => 'Australia'])->id,
        ]);

        $this->users([
            Attribute::make('countryName')
                ->visible(false)
                ->load('country')
                ->get(fn(User $user) => $user->country->name),
        ]);

        [, $queries] = $this->queries(fn() => $this->get('/users'));

        $this->assertQueryCount(0, 'countries', $queries);
    }

    public function test_loaders_run_once_in_declared_order_for_visible_models()
    {
        $country = Country::create(['name' => 'Australia']);
        User::create(['name' => 'Toby', 'country_id' => $country->id]);
        User::create(['name' => 'Hidden', 'country_id' => $country->id]);
        User::create(['name' => 'Franz', 'country_id' => $country->id]);

        $calls = [];

        $this->users([
            Attribute::make('countryName')
                ->visible(fn(User $user) => $user->name !== 'Hidden')
                ->load('country')
                ->load(function (Collection $users) use (&$calls) {
                    $calls[] = [
                        $users->pluck('id')->all(),
                        $users->every(fn(User $user) => $user->relationLoaded('country')),
                    ];
                })
                ->get(fn(User $user) => $user->country->name),
        ]);

        $this->get('/users');

        $this->assertSame([[[1, 3], true]], $calls);
    }

    public function test_closure_loaders_alone_defer_the_value()
    {
        User::create(['name' => 'Toby']);
        User::create(['name' => 'Franz']);

        $calls = 0;

        $this->users([
            Attribute::make('postCount')->load(function (Collection $users) use (&$calls) {
                $calls++;
                $users->loadCount('posts as post_count');
            })->get(fn(User $user) => $user->post_count),
        ]);

        $document = $this->document($this->get('/users'));

        $this->assertSame(1, $calls);
        $this->assertSame([0, 0], $this->attributeValues($document['data'], 'postCount'));
    }

    public function test_loads_in_one_query_for_included_models()
    {
        $country = Country::create(['name' => 'Australia']);
        User::create(['name' => 'Toby', 'country_id' => $country->id]);
        User::create(['name' => 'Franz', 'country_id' => $country->id]);
        Post::create(['title' => 'A', 'user_id' => 1]);
        Post::create(['title' => 'B', 'user_id' => 2]);

        $this->users([
            Attribute::make('countryName')->load(
                'country',
            )->get(fn(User $user) => $user->country->name),
        ]);

        $this->api->resource(
            new TestResource(
                'posts',
                Post::class,
                endpoints: [Index::make()],
                fields: [ToOne::make('author')->type('users')->includable()],
            ),
        );

        [$response, $queries] = $this->queries(fn() => $this->get('/posts?include=author'));

        $this->assertSame(
            ['Australia', 'Australia'],
            $this->attributeValues($this->document($response)['included'], 'countryName'),
        );
        $this->assertQueryCount(1, 'countries', $queries);
    }

    public function test_loads_for_resource_meta()
    {
        User::create([
            'name' => 'Toby',
            'country_id' => Country::create(['name' => 'Australia'])->id,
        ]);

        $this->users(meta: [
            Attribute::make('countryName')->load(
                'country',
            )->get(fn(User $user) => $user->country->name),
        ]);

        $this->assertSame(
            'Australia',
            $this->document($this->get('/users'))['data'][0]['meta']['countryName'],
        );
    }

    public function test_deferred_getters_are_still_batched()
    {
        $country = Country::create(['name' => 'Australia']);
        User::create(['name' => 'Toby', 'country_id' => $country->id]);
        User::create(['name' => 'Franz', 'country_id' => $country->id]);

        $log = [];

        $this->users([
            Attribute::make('countryName')->load('country')->get(function (User $user) use (&$log) {
                $log[] = "get:$user->id";

                return function () use ($user, &$log) {
                    $log[] = "resolve:$user->id";

                    return $user->country->name;
                };
            }),
        ]);

        [, $queries] = $this->queries(fn() => $this->get('/users'));

        $this->assertSame(['get:1', 'get:2', 'resolve:1', 'resolve:2'], $log);
        $this->assertQueryCount(1, 'countries', $queries);
    }

    public function test_models_from_an_unfinished_serialization_are_not_loaded_later()
    {
        User::create(['name' => 'Toby']);

        $loaded = [];

        $field = Attribute::make('name')->load(function (Collection $users) use (&$loaded) {
            $loaded[] = $users->pluck('name')->all();
        });

        $this->users([$field]);

        $context = (new Context($this->api, $this->buildRequest('GET', '/users')))
            ->withSerializer(new Serializer())
            ->withModel(new User(['name' => 'Stale']));

        $field->getValue($context);

        $this->get('/users');

        $this->assertSame([['Toby']], $loaded);
    }

    private function users(array $fields = [], array $meta = []): void
    {
        $this->api->resource(
            new TestResource(
                'users',
                User::class,
                endpoints: [Index::make()],
                fields: $fields,
                meta: $meta,
            ),
        );
    }
}
