# Index Endpoint

The `Index` endpoint handles GET requests to the collection root (e.g.
`GET /posts`) and responds with a JSON:API document containing a collection of
resources.

To enable it for a resource or collection, add an instance of the `Index`
endpoint to the `endpoints` array:

```php
use Tobyz\JsonApiServer\Endpoint\Index;

class PostsResource extends Resource
{
    // ...

    public function endpoints(): array
    {
        return [Index::make()];
    }
}
```

## Authorization

If you want to restrict the ability to list resources, use the `visible` or
`hidden` method, with a closure that returns a boolean value:

```php
Index::make()->visible(
    fn(Context $context) => $context->request->getAttribute('isAdmin'),
);
```

## Implementation

The `Index` endpoint requires the resource or collection to implement the
`Tobyz\JsonApiServer\Resource\Listable` interface. The endpoint will:

1. Call the `query` method to create a query object.
2. Apply [filters](#filtering), [sorts](#sorting), and [pagination](#pagination)
   to the query object.
3. Call the `results` method to retrieve results from the query.

An example implementation might look like:

```php
use App\Models\Post;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Resource\{Listable, AbstractResource};

class PostsResource extends AbstractResource implements Listable
{
    // ...

    public function query(Context $context): object
    {
        return Post::query();
    }

    public function results(object $query, Context $context): array
    {
        return $query->get();
    }
}
```

::: tip Laravel Integration  
For Laravel applications with Eloquent-backed resources, you can extend the
`Tobyz\JsonApiServer\Laravel\EloquentResource` class which implements this
interface for you. Learn more on the
[Laravel Integration](laravel.md#eloquent-resources) page.  
:::

## Sorting

The JSON:API specification reserves the `sort` query parameter for
[sorting resources](https://jsonapi.org/format/#fetching-sorting). Multiple sort
fields are comma-separated, and a `-` prefix indicates descending order. For
example, the following request would list posts sorted by `title` ascending,
then `createdAt` descending:

```http
GET /posts?sort=title,-createdAt
```

To define sort fields that can be used in this query parameter, add them to your
resource's `sorts` method:

```php
class PostsResource extends Resource implements Listable
{
    // ...

    public function sorts(): array
    {
        return [
            ExampleSort::make('example'), // [!code ++]
        ];
    }
}
```

::: tip Laravel Integration  
For Eloquent-backed resources, a number of [sort fields](laravel.md#sorting) are
provided to make it easy to implement sorting on your resource.  
:::

### Inline Sort Fields

The easiest way to define a custom sort field is to use the `CustomSort` class,
which accepts the name of the sort field and a callback to apply the sort to the
query:

```php
use Tobyz\JsonApiServer\Schema\CustomSort;

CustomSort::make('example', function (
    $query,
    string $direction,
    Context $context,
) {
    $query->orderBy('example', $direction);
});
```

### Writing Sort Fields

To create your own sort field class, extend the
`Tobyz\JsonApiServer\Schema\Sort` class and implement the `apply` method:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Sort;

class SortColumn extends Sort
{
    public function apply(
        object $query,
        string $direction,
        Context $context,
    ): void {
        $query->orderBy($this->name, $direction);
    }
}
```

### Visibility

If you want to restrict the ability to use a sort field, use the `visible` or
`hidden` method, passing a closure that returns a boolean value:

```php
SortColumn::make('example')->visible(
    fn(Context $context) => $context->request->getAttribute('isAdmin'),
);
```

### Default Sort

You can set a default sort to be used when none is specified by the client. To
do this, use the `defaultSort` method on the `Index` endpoint:

```php
Index::make()->defaultSort('-createdAt,title');
```

## Filtering

The JSON:API specification reserves the `filter` query parameter for
[filtering resources](https://jsonapi.org/format/#fetching-filtering).

To define filters that can be used in this query parameter, add them to your
resource's `filters` method:

```php
class PostsResource extends Resource implements Listable
{
    // ...

    public function filters(): array
    {
        return [
            ExampleFilter::make('example'), // [!code ++]
        ];
    }
}
```

::: tip Laravel Integration  
For Eloquent-backed resources, a number of [filters](laravel.md#filters) are
provided to make it easy to implement filtering on your resource.  
:::

### Inline Filters

The easiest way to define a filter is to use the `CustomFilter` class, which
accepts the name of the filter parameter and a callback to apply the filter to
the query. The value received by a filter can be a string or an array, so you
will need to handle both:

```php
use Tobyz\JsonApiServer\Schema\CustomFilter;

CustomFilter::make('name', function (
    $query,
    string|array $value,
    Context $context,
) {
    $query->whereIn('name', (array) $value);
});
```

Now the filter can be applied like so:

```http
GET /posts?filter[name]=Toby
GET /posts?filter[name][]=Toby&filter[name][]=Franz
```

### Boolean Filters

By default it is assumed that each filter applied to the query will be combined
with a logical `AND`. When a resource implements
`Tobyz\\JsonApiServer\\Resource\\SupportsBooleanFilters` you can express more
complex logic with `AND`, `OR`, and `NOT` groups.

Boolean groups are expressed by nesting objects under the `filter` parameter.
You may use either associative objects or indexed lists of clauses. Each clause
can be another filter or another boolean group.

```http
GET /posts
  ?filter[and][0][status]=published
  &filter[and][1][or][0][views][gt]=100
  &filter[and][1][or][1][not][status]=archived
```

In this request every result must be published, and it must also either have
more than 100 views or it is not archived.

```http
GET /posts
  ?filter[or][0][status]=draft
  &filter[or][1][status]=published
  &filter[or][1][not][comments]=0
```

This request returns drafts, or posts that are published and have comments. The
second example also shows that in certain cases you can omit `[and]` groups and
numeric indices; sibling filters at the same level default to `AND` behaviour.

### Writing Filters

To create your own filter class, extend the `Tobyz\JsonApiServer\Schema\Filter`
class and implement the `apply` method:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;

class WhereIn extends Filter
{
    public function apply(
        object $query,
        string|array $value,
        Context $context,
    ): void {
        $query->whereIn($this->name, $value);
    }
}
```

### Visibility

If you want to restrict the ability to use a filter, use the `visible` or
`hidden` method, passing a closure that returns a boolean value:

```php
WhereIn::make('example')->visible(
    fn(Context $context) => $context->request->getAttribute('isAdmin'),
);
```

## Pagination

The JSON:API specification reserves the `page` query parameter for
[paginating collections](https://jsonapi.org/format/#fetching-pagination). The
specification is agnostic about the pagination strategy used by the server.

json-api-server supports both offset and cursor pagination strategies. Offset
pagination uses the `page[limit]` and `page[offset]` query parameters, while
cursor pagination follows the
[`ethanresnick/cursor-pagination` profile](https://jsonapi.org/profiles/ethanresnick/cursor-pagination/)
and relies on the `page[size]`, `page[after]`, and `page[before]` parameters.

### Offset Pagination

In order to use offset pagination for your resource listing, call the `paginate`
method on the `Index` endpoint:

```php
Index::make()->paginate();
```

The default page limit is 20 and the maximum limit that a client can request
is 50. If you would like to use different values, pass them as arguments to the
`paginate` method:

```php
Index::make()->paginate(defaultLimit: 10, maxLimit: 100);
```

You will also need to implement the `Tobyz\JsonApiServer\Resource\Paginatable`
interface on your resource and return a page of results:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Page;
use Tobyz\JsonApiServer\Resource\{Listable, Paginatable, AbstractResource};

class PostsResource extends AbstractResource implements Listable, Paginatable
{
    public function paginate(
        object $query,
        int $offset,
        int $limit,
        Context $context,
    ): Page {
        return new Page(
            results: $this->results(
                $query->offset($offset)->limit($limit + 1),
                $context,
            ),
            isLastPage: count($results) <= $limit,
        );
    }
}
```

### Cursor Pagination

Cursor pagination is enabled by calling the `cursorPaginate` method on the
`Index` endpoint:

```php
Index::make()->cursorPaginate();
```

By default the page size is 20 with a maximum of 50. You can customise these
values by passing arguments:

```php
Index::make()->cursorPaginate(defaultSize: 25, maxSize: 100);
```

Cursor pagination requires implementing the
`Tobyz\JsonApiServer\Resource\CursorPaginatable` interface on the resource. The
`cursorPaginate` method must return page of results, while the `itemCursor`
method must return a cursor for the given model/query.

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\CursorPagination;
use Tobyz\JsonApiServer\Pagination\Page;
use Tobyz\JsonApiServer\Resource\CursorPaginatable;

class PostsResource extends AbstractResource implements
    Listable,
    CursorPaginatable
{
    public function cursorPaginate(
        object $query,
        int $size,
        ?string $after,
        ?string $before,
        Context $context,
    ): Page {
        // ...

        return new Page($results, $isFirstPage, $isLastPage);
    }

    public function itemCursor($model, object $query, Context $context): string
    {
        // ...
    }
}
```

### Countability

By default, offset pagination won't include a `last` link because there is no
way to know the total number of resources.

If you would like to include a `last` link, as well as include the total number
of resources as `meta` information, you can implement the
`Tobyz\JsonApiServer\Resource\Countable` interface on your resource:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Resource\{
    Countable,
    Listable,
    Paginatable,
    AbstractResource,
};

class PostsResource extends AbstractResource implements
    Listable,
    Paginatable,
    Countable
{
    // ...

    public function count(object $query, Context $context): int
    {
        return $query->count();
    }
}
```
