# Create Endpoint

The `Create` endpoint handles POST requests to the collection root (e.g.
`POST /posts`) and responds with a JSON:API document containing the created
resource object.

To enable it for a resource or collection, add the `Create` endpoint to the
`endpoints` array:

```php
use Tobyz\JsonApiServer\Endpoint\Create;

class PostsResource extends Resource
{
    // ...

    public function endpoints(): array
    {
        return [Create::make()];
    }
}
```

## Authorization

If you want to restrict the ability to create resources, use the `visible` or
`hidden` method, with a closure that returns a boolean value:

```php
Create::make()->visible(
    fn(Context $context) => $context->request->getAttribute('isAdmin'),
);
```

## Implementation

The `Create` endpoint requires the resource or collection to implement the
`Tobyz\JsonApiServer\Resource\Creatable` interface (which overlaps with the
`Updatable` interface). The endpoint will:

1. Call the `newModel` method to get a new model instance.
2. Deserialize and validate field data.
3. Call the `setValue` method for each field.
4. Call the `create` method to persist the model to storage.
5. Call the `saveValue` method for each field.

A simple implementation might look like:

```php
use App\Models\Post;
use Tobyz\JsonApiServer\Resource\Creatable;

class PostsResource extends Resource implements Creatable
{
    // ...

    public function endpoints(): array
    {
        return [Endpoint\Create::make()];
    }

    public function newModel(Context $context): object
    {
        return new Post();
    }

    public function setValue(
        object $model,
        Field $field,
        mixed $value,
        Context $context,
    ): void {
        $model->{$field->property ?: $field->name} = $value;
    }

    public function create(object $model, Context $context): object
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
