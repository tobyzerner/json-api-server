# Defining Resources

## Resources & Collections

**Resources** describe the different types of resources that exist within your
JSON:API server, their field schema, and behaviour for create, update, and
delete operations.

**Collections** describe the API endpoints through which these resources are
exposed, and behaviour for querying and retrieving resource data from your
storage.

Most of the time, collections are homogenous, meaning they contain a single kind
of resource. For example, a GET request to the `/posts` collection will return a
list of `posts` resources.

When you define a resource type by extending the `AbstractResource` class, a
collection for that resource type is automatically defined as well. In addition
to the resource's field schema and storage behaviour, you can define the
collection's endpoints and behaviour directly on your resource class.

It is also possible for collections to be heterogeneous – containing multiple
kinds of resources. See [Heterogeneous Collections](collections) for more
information.

## Defining Resources

To define a resource type within your API, create a new class that extends
`Tobyz\JsonApi\Resource\AbstractResource`, and implement the `type()` method to
return the name of your resource type. This will also be used as the collection
path for your resource.

```php
use Tobyz\JsonApiServer\Resource\AbstractResource;

class PostsResource extends AbstractResource
{
    public function type(): string
    {
        return 'posts';
    }
}
```

## Registering Resources

Register an instance of your resource class with the API server using the
`resource()` method:

```php
$api = new JsonApi();

$api->resource(new PostsResource());
```

## Identifier

Every JSON:API resource has an `id` field. You can customize how the ID is
handled by defining an `id()` method on your resource that returns an `Id` field
instance:

```php
use Tobyz\JsonApiServer\Schema\Id;

class PostsResource extends AbstractResource
{
    // ...

    public function id(): Id
    {
        return Id::make()
            ->writableOnCreate() // Allow client-generated IDs
            ->validate(fn($value, $fail) => /* ... */);
    }
}
```

If you don't define an `id()` method, a default implementation will be used that
reads the `id` property from your model.

Learn more about customizing the [Resource ID](id.md), including
client-generated IDs and validation.

## Fields

A resource object's attributes and relationships are collectively called its
"fields".

Each resource class contains a `fields()` method which returns an array of field
objects. These will be used to serialize models into JSON:API resource objects.

The following example demonstrates some basic field definitions. You can learn
more about [field definitions](fields.md), as well as
[attributes](attributes.md) and [relationships](relationships.md).

```php
use Tobyz\JsonApiServer\Schema\Field;
use Tobyz\JsonApiServer\Schema\Type;

class PostsResource extends AbstractResource
{
    // ...

    public function fields(): array
    {
        return [
            Field\Attribute::make('title')
                ->type(Type\Str::make())
                ->writable(),

            Field\Attribute::make('body')
                ->type(Type\Str::make())
                ->writable(),

            Field\Attribute::make('createdAt')
                ->type(Type\DateTime::make())
                ->default(fn() => new DateTime()),

            Field\ToOne::make('author')
                ->type('users')
                ->includable(),

            Field\ToMany::make('comments'),
        ];
    }
}
```

## Endpoints

In order to expose endpoints for listing, creating, reading, updating, and
deleting your resource, you will need to implement the `endpoints()` method on
the resource class and return an array of endpoint objects. These are classes
that can handle an incoming request and return a response.

```php
use Tobyz\JsonApiServer\Endpoint;

class UsersResource extends AbstractResource
{
    // ...

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make(),
            Endpoint\Create::make(),
            Endpoint\Show::make(),
            Endpoint\Update::make(),
            Endpoint\Delete::make(),
        ];
    }
}
```

The main endpoints available for use are listed in the table below. Each of
these endpoints requires the implementation of an interface on your class to
define the behavior of how the endpoint should interact with your storage.

| Endpoint            | Requests                                                                                | Interface   |
| ------------------- | --------------------------------------------------------------------------------------- | ----------- |
| [Index](list.md)    | `GET /users`                                                                            | `Listable`  |
| [Create](create.md) | `POST /users`                                                                           | `Creatable` |
| [Show](show.md)     | `GET /users/{id}`<br>`GET /users/{id}/{name}`<br>`GET /users/{id}/relationships/{name}` | `Findable`  |
| [Update](update.md) | `PATCH /users/{id}`<br>`PATCH\|POST\|DELETE /users/{id}/relationships/{name}`           | `Updatable` |
| [Delete](delete.md) | `DELETE /users/{id}`                                                                    | `Deletable` |

::: tip Laravel Integration  
For Laravel applications with Eloquent-backed resources, you can extend the
`Tobyz\JsonApiServer\Laravel\EloquentResource` class which implements all of
these interfaces for you. Learn more on the
[Laravel Integration](laravel.md#eloquent-resources) page.  
:::

### Collection & Resource Actions

In addition to the standard RESTful endpoints, you can create custom "action"
endpoints for collections (`CollectionAction`) and resources (`ResourceAction`).

When instantiating these classes, pass the name of the action to be used in the
route path, and a handler which will receive the current [context](context.md)
and optionally return a response.

Collection actions will return a `204 No Content` response if nothing is
returned by the handler. Resource actions will return a `200 OK` response
including the resource data.

```php
use Tobyz\JsonApiServer\Context;
use Psr\Http\Message\ResponseInterface;

Endpoint\CollectionAction::make('test', function (
    Context $context,
): ?ResponseInterface {
    // ...
});

Endpoint\ResourceAction::make('test', function (
    Context $context,
): ?ResponseInterface {
    // ...
});
```

The `POST` method is used by default, but you can customize this using the
`method` method:

```php
Endpoint\CollectionAction::make(...)->method('PUT');
```
