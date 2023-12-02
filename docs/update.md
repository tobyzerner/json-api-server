# Update Endpoint

The `Update` endpoint handles PATCH requests to resources (e.g.
`PATCH /posts/1`) and responds with a JSON:API document containing the updated
resource object.

To enable it for a resource or collection, add the `Update` endpoint to the
`endpoints` array:

```php
use Tobyz\JsonApiServer\Endpoint\Update;

class PostsResource extends Resource
{
    // ...

    public function endpoints(): array
    {
        return [Update::make()];
    }
}
```

## Authorization

If you want to restrict the ability to update a resource, use the `visible` or
`hidden` method, with a closure that returns a boolean value:

```php
Update::make()->visible(fn($model, Context $context) => $model->is_wiki);
```

## Implementation

The `Update` endpoint requires the resource or collection to implement the
`Tobyz\JsonApiServer\Resource\Updatable` interface (which extends the `Findable`
interface, and overlaps with the `Creatable` interface). The endpoint will:

1. Call the `find` method to retrieve the model instance.
2. Deserialize and validate field data.
3. Call the `setValue` method for each field.
4. Call the `update` method to persist the model to storage.
5. Call the `saveValue` method for each field.

A simple implementation might look like:

```php
use App\Models\Post;
use Tobyz\JsonApiServer\Resource\Updatable;

class PostsResource extends Resource implements Updatable
{
    // ...

    public function endpoints(): array
    {
        return [Endpoint\Update::make()];
    }

    public function find(string $id, Context $context): ?object;
    {
        return Post::find($id);
    }

    public function setValue(
        object $model,
        Field $field,
        mixed $value,
        Context $context,
    ): void {
        $model->{$field->property ?: $field->name} = $value;
    }

    public function update(object $model, Context $context): object
    {
        $post->save();
    }

    public function saveValue(
        object $model,
        Field $field,
        mixed $value,
        Context $context,
    ): void {
        // noop
    }
}
```

::: tip Laravel Integration  
For Laravel applications with Eloquent-backed resources, you can extend the
`Tobyz\JsonApiServer\Laravel\EloquentResource` class which implements this
interface for you. Learn more on the
[Laravel Integration](laravel.md#eloquent-resources) page.  
:::
