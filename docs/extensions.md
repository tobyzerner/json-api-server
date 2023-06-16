# Extensions

[Extensions](https://jsonapi.org/format/1.1/#extensions) allow your API to
support additional functionality that is not part of the base specification.

## Defining Extensions

Extensions can be defined by extending the
`Tobyz\JsonApiServer\Extension\Extension` class and implementing two methods:
`uri` and `handle`.

You must return your extension's unique URI from `uri`.

For every request that includes your extension in the media type, the `handle`
method will be called. If your extension is able to handle the request, it
should return a PSR-7 response. Otherwise, return `null` to let the normal
handling of the request take place.

```php
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context\Context;
use Tobyz\JsonApiServer\Extension\Extension;

use function Tobyz\JsonApiServer\json_api_response;

class MyExtension extends Extension
{
    public function uri(): string
    {
        return 'https://example.org/my-extension';
    }

    public function handle(Context $context): ?ResponseInterface
    {
        if ($context->path() === 'my-extension') {
            return json_api_response([
                'my-extension:greeting' => 'Hello world!',
            ]);
        }

        return null;
    }
}
```

::: warning  
The current implementation of extensions has no support for augmentation of
standard API responses. This API may change dramatically in the future. Please
[create an issue](https://github.com/tobyzerner/json-api-server/issues/new) if
you have a specific use-case you want to achieve.  
:::

## Registering Extensions

Extensions can be registered on your API server using the `extension` method:

```php
use Tobyz\JsonApiServer\JsonApi;

$api = new JsonApi();

$api->extension(new MyExtension());
```

The API server will automatically perform appropriate
[content negotiation](https://jsonapi.org/format/1.1/#content-negotiation-servers)
and activate the specified extensions on each request.

## Atomic Operations

An implementation of the [Atomic Operations](https://jsonapi.org/ext/atomic/)
extension is available at `Tobyz\JsonApi\Extension\Atomic`.

When using this extension, you are responsible for wrapping the `$api->handle`
call in a transaction to ensure any database (or other) operations performed are
actually atomic in nature. For example, in Laravel:

```php
use Illuminate\Support\Facades\DB;
use Tobyz\JsonApiServer\Extension\Atomic;
use Tobyz\JsonApiServer\JsonApi;

$api = new JsonApi();

$api->extension(new Atomic());

return DB::transaction(fn() => $api->handle($request));
```
