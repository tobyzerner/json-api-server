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

Currently json-api-server supports an offset pagination strategy, using the
`page[limit]` and `page[offset]` query parameters. Support for cursor pagination
is planned.

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
Index::make()->paginate(10, 100);
```

You will also need to implement the `Tobyz\JsonApiServer\Resource\Paginatable`
interface on your resource and specify how the limit and offset values should be
applied to your query:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Resource\{Listable, Paginatable, AbstractResource};

class PostsResource extends AbstractResource implements Listable, Paginatable
{
    // ...

    public function paginate(object $query, OffsetPagination $pagination): void
    {
        $query->offset($pagination->offset)->limit($pagination->limit);
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
