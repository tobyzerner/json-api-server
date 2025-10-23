# Update Endpoint

The `Update` endpoint handles PATCH requests to resources (e.g.
`PATCH /posts/1`) and responds with a JSON:API document containing the updated
resource object.

The `Update` endpoint also handles PATCH, POST, and DELETE requests to
relationship URLs (e.g. `GET /posts/1/relationships/author`).

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

## Post-processing

If you need to perform work after every field has been saved but before the
response document is produced, register a `saved` callback:

```php
Update::make()->saved(function ($model, Context $context) {
    // e.g. refresh aggregates or mutate the context prior to serialization
});
```

## Modifying To-Many Relationships

By default, the JSON:API `POST` and `DELETE` relationship routes behave like a
full replacement: the provided resource identifiers are merged into (or removed
from) the relationship value and then persisted via the normal `setValue` /
`saveValue` flow.

If you need finer control – such as running domain logic when attaching or
detaching related models – mark the field as attachable and implement the
`Tobyz\JsonApiServer\Resource\Attachable` contract on the owning resource.

```php
use Tobyz\JsonApiServer\Resource\Attachable;
use Tobyz\JsonApiServer\Schema\Field\ToMany;

class PostsResource extends Resource implements Updatable, Attachable
{
    public function fields(): array
    {
        return [
            ToMany::make('tags')
                ->type('tags')
                ->writable()
                ->attachable()
                ->validateAttach(function (
                    $fail,
                    array $related,
                    $model,
                    $context,
                ) {
                    foreach ($related as $index => $candidate) {
                        if ($candidate->id === $model->id) {
                            $fail('A post cannot tag itself.', $index);
                        }
                    }
                }),
        ];
    }

    public function attach(
        $model,
        $relationship,
        array $related,
        $context,
    ): void {
        foreach ($related as $tag) {
            $model->tags[] = $tag;
        }
    }

    public function detach(
        $model,
        $relationship,
        array $related,
        $context,
    ): void {
        $ids = array_map(fn($tag) => $tag->id, $related);

        $model->tags = array_values(
            array_filter($model->tags, fn($tag) => !in_array($tag->id, $ids)),
        );
    }
}
```

The `validateAttach` and `validateDetach` helpers receive the full array of
resolved models. Call the `$fail` callback – optionally passing the zero-based
index of an offending entry – to surface `422 Unprocessable Entity` responses
with the correct JSON:API `source` pointers. Use `validateDetach` to apply the
same guard rails when removing related records.
