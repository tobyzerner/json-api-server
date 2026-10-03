<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Laravel\Sort\SortColumn;
use Tobyz\JsonApiServer\Laravel\Sort\SortWithCount;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\laravel\Models\Comment;
use Tobyz\Tests\JsonApiServer\laravel\Models\Post;

class EloquentSortTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Post::create(['title' => 'Banana', 'published_at' => '2024-03-01 00:00:00']);
        Post::create(['title' => 'Apple', 'published_at' => '2024-01-01 00:00:00']);
        Post::create(['title' => 'Cherry', 'published_at' => '2024-02-01 00:00:00']);

        foreach ([[1, 'x'], [1, 'y'], [3, 'x'], [3, 'x'], [3, 'y']] as [$post, $body]) {
            Comment::create(['post_id' => $post, 'body' => $body]);
        }
    }

    public function test_sort_column()
    {
        $this->posts([SortColumn::make('title'), SortColumn::make('publishedAt')]);

        $this->assertSame(['2', '1', '3'], $this->sort('title'));
        $this->assertSame(['3', '1', '2'], $this->sort('-title'));
        $this->assertSame(['2', '3', '1'], $this->sort('publishedAt'));
    }

    public function test_sort_column_with_custom_column()
    {
        $this->posts([SortColumn::make('date')->column('published_at')]);

        $this->assertSame(['1', '3', '2'], $this->sort('-date'));
    }

    public function test_sort_with_count()
    {
        $this->posts([SortWithCount::make('comments')]);

        $this->assertSame(['3', '1', '2'], $this->sort('-comments'));
        $this->assertSame(['2', '1', '3'], $this->sort('comments'));
    }

    public function test_sort_with_count_scope_and_alias()
    {
        $this->posts([
            SortWithCount::make('xComments')
                ->relationship('comments')
                ->scope(fn($query) => $query->where('body', 'x'))
                ->countAs('x_count'),
        ], [Attribute::make('xCount')->property('x_count')]);

        $response = $this->get('/posts?sort=-xComments');

        $this->assertSame(['3', '1', '2'], $this->ids($response));
        $this->assertSame([2, 1, 0], $this->attributeValues($this->document($response)['data'], 'xCount'));
    }

    /**
     * @return string[] Post IDs in the order returned for a sort.
     */
    private function sort(string $sort): array
    {
        return $this->ids($this->get("/posts?sort=$sort"));
    }

    private function posts(array $sorts, array $fields = []): void
    {
        $this->api->resource(
            new TestResource(
                'posts',
                Post::class,
                endpoints: [Index::make()],
                fields: [Attribute::make('title'), ...$fields],
                sorts: $sorts,
            ),
        );
    }
}
