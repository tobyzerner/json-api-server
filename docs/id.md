# ID

Every JSON:API resource has an `id` field that uniquely identifies it. By
default, the `id` is managed automatically by your resource implementation, but
you can customize its behavior using the `Id` field class.

The `Id` field extends the base `Field` class, so all [field methods](fields.md)
like `required()`, `default()`, and `validate()` are available.

## Customizing the ID Field

To customize how the ID is handled, define an `id()` method on your resource
that returns an `Id` field instance:

```php
use Tobyz\JsonApiServer\Schema\Id;

class PostsResource extends AbstractResource
{
    // ...

    public function id(): Id
    {
        return Id::make();
    }
}
```

## Type Constraints

The `Id` field uses a string type by default, but you can customize it to define
additional constraints:

```php
use Tobyz\JsonApiServer\Schema\Type;

Id::make()->type(Type\Str::make()->pattern('^[0-9]+$'));
```

## Client-Generated IDs

By default, resource IDs are server-generated. To allow clients to provide their
own IDs when creating resources, use the `writableOnCreate()` method:

```php
Id::make()->writableOnCreate();
```

Clients can then include an `id` in the request body when creating a resource:

```json
{
    "data": {
        "type": "posts",
        "id": "custom-id-123",
        "attributes": {
            "title": "My Post"
        }
    }
}
```

You can combine this with other field methods:

```php
Id::make()
    ->writableOnCreate()
    ->required() // Client MUST provide an ID
    ->validate(function ($value, $fail) {
        if (!preg_match('/^[a-z0-9-]+$/', $value)) {
            $fail(
                'ID must contain only lowercase letters, numbers, and hyphens',
            );
        }
    });

Id::make()
    ->writableOnCreate()
    ->default(fn() => Str::uuid()->toString()); // Fallback if not provided
```
