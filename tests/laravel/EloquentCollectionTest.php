<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Closure;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Laravel\UnionBuilder;
use Tobyz\JsonApiServer\Schema\CustomSort;
use Tobyz\Tests\JsonApiServer\laravel\Models\Post;
use Tobyz\Tests\JsonApiServer\laravel\Models\User;

class EloquentCollectionTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::create(['name' => 'Toby']);
        User::create(['name' => 'Admin', 'is_admin' => true]);
        Post::create(['title' => 'A']);
        Post::create(['title' => 'B']);

        $this->resources(scopes: ['users' => fn($query) => $query->where('is_admin', false)]);
    }

    public function test_lists_models_of_each_resource_through_their_scopes()
    {
        $this->feed();

        $document = $this->document($this->get('/feed?sort=type'));

        $this->assertSame(
            [
                ['posts', '1', 'A'],
                ['posts', '2', 'B'],
                ['users', '1', 'Toby'],
            ],
            array_map(fn($resource) => [
                $resource['type'],
                $resource['id'],
                $resource['attributes']['title'] ?? $resource['attributes']['name'],
            ], $document['data']),
        );
    }

    public function test_paginates_and_counts()
    {
        $this->feed(paginate: true);

        $first = $this->document($this->get('/feed?sort=type&page[limit]=2'));
        $this->assertSame(['posts', 'posts'], array_column($first['data'], 'type'));
        $this->assertSame(3, $first['meta']['page']['total']);

        $second = $this->document($this->get('/feed?sort=type&page[limit]=2&page[offset]=2'));
        $this->assertSame([['users', '1']], array_map(fn($r) => [$r['type'], $r['id']], $second['data']));
    }

    public function test_collection_scope_and_per_resource_queries()
    {
        $this->feed(scope: fn(UnionBuilder $query) => $query->for('posts')->where('title', 'B'));

        $document = $this->document($this->get('/feed?sort=type'));

        $this->assertSame(
            [['posts', '2'], ['users', '1']],
            array_map(fn($r) => [$r['type'], $r['id']], $document['data']),
        );
    }

    private function feed(bool $paginate = false, ?Closure $scope = null): void
    {
        $this->api->collection(
            new TestCollection(
                'feed',
                ['users', 'posts'],
                endpoints: [$paginate ? Index::make()->paginate() : Index::make()],
                sorts: [
                    CustomSort::make('type', fn(UnionBuilder $query, string $direction) => $query->outer(
                        fn($query) => $query->orderBy('type', $direction)->orderBy('id'),
                    )),
                ],
                scope: $scope,
            ),
        );
    }
}
