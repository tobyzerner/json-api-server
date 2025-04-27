# json-api-server

[![Pre Release](https://img.shields.io/packagist/vpre/tobyz/json-api-server.svg?style=flat)](https://github.com/tobyzerner/json-api-server/releases)
[![License](https://img.shields.io/packagist/l/tobyz/json-api-server.svg?style=flat)](https://packagist.org/packages/tobyz/json-api-server)

json-api-server is a [JSON:API](http://jsonapi.org) server implementation in
PHP.

It allows you to build a feature-rich API by defining resource schema and
connecting it to your application's database layer.

Based on your schema definition, the package will serve a complete API that
conforms to the [JSON:API specification](https://jsonapi.org/format/), including
support for:

- **Showing** individual resources (`GET /articles/1`)
- **Listing** resource collections (`GET /articles`)
- **Sorting**, **filtering**, **pagination**, and **sparse fieldsets**
- **Compound documents** with inclusion of related resources
- **Creating** resources (`POST /articles`)
- **Updating** resources (`PATCH /articles/1`)
- **Deleting** resources (`DELETE /articles/1`)
- **Content negotiation**
- **Error handling**
- **Extensions** including Atomic Operations
- **Generating OpenAPI definitions**

## Documentation

[Read the documentation](https://tobyzerner.github.io/json-api-server)

## Example

The following example uses an Eloquent model in a Laravel application. However,
json-api-server can be used with any framework that can deal in PSR-7 Requests
and Responses. Custom behavior can be implemented to support other ORMs and data
persistence layers.

```php
use App\Models\User;
use Tobyz\JsonApiServer\Laravel;
use Tobyz\JsonApiServer\Laravel\EloquentResource;
use Tobyz\JsonApiServer\Laravel\Filter;
use Tobyz\JsonApiServer\Endpoint;
use Tobyz\JsonApiServer\Schema\Field;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\JsonApiServer\JsonApi;

class UsersResource extends EloquentResource
{
    public function type(): string
    {
        return 'users';
    }

    public function newModel(Context $context): object
    {
        return new User();
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make(),
            Endpoint\Index::make()->paginate(),
            Endpoint\Create::make()->visible(Laravel\can('create')),
            Endpoint\Update::make()->visible(Laravel\can('update')),
            Endpoint\Delete::make()->visible(Laravel\can('delete')),
        ];
    }

    public function fields(): array
    {
        return [
            Field\Attribute::make('name')
                ->type(Type\Str::make())
                ->writable()
                ->required(),

            Field\ToOne::make('address')->includable(),

            Field\ToMany::make('friends')
                ->type('users')
                ->includable(),
        ];
    }

    public function filters(): array
    {
        return [Filter\Where::make('id'), Filter\Where::make('name')];
    }
}

$api = new JsonApi();

$api->resource(new UsersResource());

/** @var Psr\Http\Message\ServerRequestInterface $request */
/** @var Psr\Http\Message\ResponseInterface $response */
try {
    $response = $api->handle($request);
} catch (Throwable $e) {
    $response = $api->error($e);
}
```

## Contributing

Pull requests are welcome. For major changes, please open an issue first to
discuss what you would like to change.

## License

[MIT](LICENSE)
