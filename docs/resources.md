# Defining Resources

Resource classes describe the types of JSON:API resources that exist within your
server, and the endpoints that they expose.

They also define behavior for how the JSON:API server interacts with your
storage to query, create, read, update, and delete resources.

## Defining Resources

To define a resource type within your API, create a new class that extends
`Tobyz\JsonApi\Resource\Resource`, and implement the `type()` method to return
the name of your resource type:

```php
use Tobyz\JsonApiServer\Resource\Resource;

class PostsResource extends Resource
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

When serializing a model into a JSON:API resource object, the `getId` method on
your resource class will be used to get the `id` for the model. A default
implementation is provided in the `Resource` class which assumes that your
models have an `id` property. You may override this if needed:

```php
use Tobyz\JsonApiServer\Context;

class PostsResource extends Resource
{
    // ...

    public function getId(object $model, Context $context): string
    {
        return $model->getKey();
    }
}
```

## Fields

A resource object's attributes and relationships are collectively called its
"fields".

Each resource class contains a `fields()` method which returns an array of field
objects. These will be used to serialize models into JSON:API resource objects.

The following example demonstrates some basic field definitions. You can learn
more about [field definitions](fields.md), as well as the available types of
[attributes](attributes.md) and [relationships](relationships.md).

<!-- prettier-ignore -->
```php
use Tobyz\JsonApiServer\Field;

class PostsResource extends Resource
{
    // ...

    public function fields(): array
    {
        return [
            Field\Str::make('title')->writable(),
            Field\Str::make('body')->writable(),
            Field\DateTime::make('createdAt'),
            Field\ToOne::make('author')->type('users')->includable(),
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

class UsersResource extends Resource
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
these endpoints requires the implementation of an interface on your resource
class to define the behavior of how the endpoint should interact with your
storage.

| Method | URI           | Endpoint            | Interface                                |
| ------ | ------------- | ------------------- | ---------------------------------------- |
| GET    | `/users`      | [Index](index.md)   | `Tobyz\JsonApiServer\Resource\Listable`  |
| POST   | `/users`      | [Create](create.md) | `Tobyz\JsonApiServer\Resource\Creatable` |
| GET    | `/users/{id}` | [Show](show.md)     | `Tobyz\JsonApiServer\Resource\Findable`  |
| PATCH  | `/users/{id}` | [Update](update.md) | `Tobyz\JsonApiServer\Resource\Updatable` |
| DELETE | `/users/{id}` | [Delete](delete.md) | `Tobyz\JsonApiServer\Resource\Deletable` |

::: tip Laravel Integration  
For Laravel applications with Eloquent-backed resources, you can extend the
`Tobyz\JsonApiServer\Laravel\EloquentResource` class which implements all of
these interfaces. Learn more on the
[Laravel Integration](laravel.md#eloquent-resources) page.  
:::
