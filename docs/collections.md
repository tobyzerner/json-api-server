# Heterogeneous Collections

You can define collections that are heterogeneous – containing multiple kinds of
resources. For example, an `activity` collection might contain `posts`,
`comments`, and `likes` resources.

Heterogeneous collections can be exposed in the API through endpoints. They are
also used for defining
[polymorphic relationships](relationships.md#polymorphic-relationships).

## Defining Collections

To define a heterogeneous collection, create a new class that implements the
`Tobyz\JsonApi\Resource\Collection` interface:

```php
use Tobyz\JsonApiServer\Resource\Collection;

class ActivityCollection implements Collection
{
    /**
     * Get the collection name.
     */
    public function name(): string
    {
        return 'activity';
    }

    /**
     * Get the resources contained within this collection.
     */
    public function resources(): array
    {
        return ['posts', 'comments', 'likes'];
    }

    /**
     * Get the name of the resource that represents the given model.
     */
    public function resource(object $model, Context $context): ?string
    {
        return match (true) {
            $model instanceof Post => 'posts',
            $model instanceof Comment => 'comments',
            $model instanceof Like => 'likes',
            default => null,
        };
    }

    /**
     * The collection's endpoints.
     */
    public function endpoints(): array
    {
        return [];
    }
}
```

The `name` you choose will be used as the path for any endpoints, and
relationships may also reference your collection by this name.

The `resources` array should contain the names of the resource types that can
exist within the collection. This will be used to restrict which resources can
be created or attached to this collection, as well as what relationships can be
included.

The `resource` method maps a model to the resource type that should represent it
in this collection.

The `endpoints` method should return an array of endpoint objects to expose your
collection in the API. Refer to the [Resources page](resources.md#endpoints) for
more information.

## Registering Collections

Register an instance of your collection class with the API server using the
`collection()` method:

```php
$api = new JsonApi();

$api->collection(new ActivityCollection());
```
