# Show Endpoint

The `Show` endpoint handles GET requests to resources (e.g. `GET /posts/1`) and
responds with a JSON:API document containing a single resource object.

The `Show` endpoint also handles GET requests to related resource URLs (e.g.
`GET /posts/1/author`) and relationship URLs (e.g.
`GET /posts/1/relationships/author`).

To enable it for a resource or collection, add the `Show` endpoint to the
`endpoints` array:

```php
use Tobyz\JsonApiServer\Endpoint\Show;

class PostsResource extends Resource
{
    // ...

    public function endpoints(): array
    {
        return [Show::make()];
    }
}
```

## Authorization

If you want to restrict the ability to show a resource, use the `visible` or
`hidden` method, with a closure that returns a boolean value:

```php
Show::make()->visible(fn($model, Context $context) => $model->is_public);
```

## Implementation

The `Show` endpoint requires the resource or collection to implement the
`Tobyz\JsonApiServer\Resource\Findable` interface. The endpoint will call the
`find` method with the requested resource ID to retrieve the model instance.
The method receives an array of IDs and should return the models that exist, in
any order.

A simple implementation might look like:

```php
use App\Models\Post;
use Tobyz\JsonApiServer\Resource\Findable;

class PostsResource extends Resource implements Findable
{
    // ...

    public function endpoints(): array
    {
        return [Endpoint\Show::make()];
    }

    public function find(array $ids, Context $context): array
    {
        return Post::findMany($ids)->all();
    }
}
```

::: tip Laravel Integration  
For Laravel applications with Eloquent-backed resources, you can extend the
`Tobyz\JsonApiServer\Laravel\EloquentResource` class which implements this
interface for you. Learn more on the
[Laravel Integration](laravel.md#eloquent-resources) page.  
:::
