# Delete Endpoint

The `Delete` endpoint handles DELETE requests to resources (e.g.
`DELETE /posts/1`) and responds with a `204 No Content` response.

To enable it for a resource or collection, add the `Delete` endpoint to the
`endpoints` array:

```php
use Tobyz\JsonApiServer\Endpoint\Delete;

class PostsResource extends Resource
{
    // ...

    public function endpoints(): array
    {
        return [Delete::make()];
    }
}
```

## Authorization

If you want to restrict the ability to delete a resource, use the `visible` or
`hidden` method, with a closure that returns a boolean value:

```php
Delete::make()->visible(fn($model, Context $context) => $model->is_wiki);
```

## Implementation

The `Delete` endpoint requires the resource or collection to implement the
`Tobyz\JsonApiServer\Resource\Deletable` interface (which extends the `Findable`
interface). The endpoint will:

1. Call the `find` method to retrieve the model instance.
2. Call the `delete` method to delete the model.

A simple implementation might look like:

```php
use App\Models\Post;
use Tobyz\JsonApiServer\Resource\Updatable;

class PostsResource extends Resource implements Deletable
{
    // ...

    public function endpoints(): array
    {
        return [Endpoint\Delete::make()];
    }

    public function find(string $id, Context $context): ?object;
    {
        return Post::find($id);
    }

    public function delete(object $model, Context $context): void
    {
        $model->delete();
    }
}
```

::: tip Laravel Integration  
For Laravel applications with Eloquent-backed resources, you can extend the
`Tobyz\JsonApiServer\Laravel\EloquentResource` class which implements this
interface for you. Learn more on the
[Laravel Integration](laravel.md#eloquent-resources) page.  
:::
