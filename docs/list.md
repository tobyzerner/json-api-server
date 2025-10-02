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
2. Apply [filtering](filtering.md), [sorting](sorting.md), and
   [pagination](pagination.md) to the query object.
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
