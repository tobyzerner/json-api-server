# Extensions

[Extensions](https://jsonapi.org/format/1.1/#extensions) allow your API to
support additional functionality that is not part of the base specification.

## Registering Extensions

Extensions can be registered on your API server using the `extension` method:

```php
use Tobyz\JsonApiServer\JsonApi;

$api = new JsonApi();

$api->extension(new MyExtension());
```

The API server will automatically perform
[content negotiation](https://jsonapi.org/format/1.1/#content-negotiation-servers)
on each request. An extension is only used when the client includes its URI in
the `ext` media type parameter of the `Accept` header, and of the `Content-Type`
header if the request has a body. When an extension is applied, its URI is
included in the response `Content-Type` header.

## Available Extensions

### Atomic Operations

An implementation of the [Atomic Operations](https://jsonapi.org/ext/atomic/)
extension is available at `Tobyz\JsonApiServer\Extension\Atomic\Atomic`.

When using this extension, you are responsible for wrapping the `$api->handle`
call in a transaction to ensure any database (or other) operations performed are
actually atomic in nature. For example, in Laravel:

```php
use Illuminate\Support\Facades\DB;
use Tobyz\JsonApiServer\Extension\Atomic\Atomic;
use Tobyz\JsonApiServer\JsonApi;

$api = new JsonApi();

$api->extension(new Atomic());

return DB::transaction(fn() => $api->handle($request));
```

### Relative Sparse Fieldsets

An implementation of the [relfield](https://github.com/ThorstenSuckow/relfield)
extension is available at `Tobyz\JsonApiServer\Extension\Relfield\Relfield`. It
lets clients request fields relative to a type's default fieldset (all fields
that are not [sparse](fields.md#sparse-fieldsets)) using the
`relfield:fields[TYPE]` query parameter:

```php
use Tobyz\JsonApiServer\Extension\Relfield\Relfield;

$api->extension(new Relfield());
```

```http
GET /articles/1?relfield:fields[articles]=version,-body
Accept: application/vnd.api+json; ext="https://conjoon.org/json-api/ext/relfield"
```

- Unprefixed fields are added to the default fieldset
- Fields prefixed with `-` are excluded from the default fieldset

Unknown fields are ignored. Using `relfield:fields` and `fields` for the same
type results in a `400 Bad Request` error.

## Writing Extensions

Extensions are defined by extending the
`Tobyz\JsonApiServer\Extension\Extension` class. You must implement the `uri`
method to return your extension's unique URI. If your extension introduces
document members or query parameters, implement the `namespace` method to return
the [namespace](https://jsonapi.org/format/1.1/#extension-rules) they are
prefixed with. Namespaces may only contain letters and digits.

Extensions change how requests are handled through hooks, returned from the
`hooks` method. Hooks only run for requests that include your extension in the
media type:

| Hook                                  | Purpose                                         |
| ------------------------------------- | ----------------------------------------------- |
| [`HandleRequest`](#handling-requests) | Handle a request instead of the API's endpoints |
| [`SparseFields`](#sparse-fieldsets)   | Change how sparse fieldsets are resolved        |

::: info  
Extensions can only change the behavior of standard requests through these
hooks. To add to the [OpenAPI definition](openapi.md), an extension can also
implement `Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema`. If you need a hook
that isn't available, please
[create an issue](https://github.com/tobyzerner/json-api-server/issues/new)
describing your use case.  
:::

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Extension\Extension;
use Tobyz\JsonApiServer\Extension\Hook\HandleRequest;

class MyExtension extends Extension
{
    public function uri(): string
    {
        return 'https://example.org/my-extension';
    }

    public function namespace(): string
    {
        return 'myext';
    }

    public function hooks(): array
    {
        return [
            HandleRequest::make(function (Context $context) {
                // ...
            }),
        ];
    }
}
```

### Parameters

Hooks can define the [parameters](resources.md#request-parameters) they use. A
hook's parameters are loaded and validated on the endpoints that run the hook,
and are included in the [OpenAPI definition](openapi.md) for those endpoints.
Query parameter names must be prefixed with your extension's namespace:

```php
use Tobyz\JsonApiServer\Extension\Hook\SparseFields;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;

SparseFields::make($this->sparseFields(...))->parameters([
    Parameter::make('myext:option')->type(Type\Str::make()->enum(['a', 'b'])),
]);
```

The validated value can be read in the hook using
`$context->parameter('myext:option')`. When your extension is negotiated,
unknown query parameters prefixed with its namespace are rejected with a
`400 Bad Request` error, just like unknown standard parameters.

### Handling Requests

The `HandleRequest` hook lets your extension respond to a request itself. It
runs before the request is dispatched to an endpoint, so unlike other hooks it
doesn't take parameters. If your extension is able to handle the request, return
a PSR-7 response. Otherwise, return `null` to let the normal handling of the
request take place:

```php
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Extension\Hook\HandleRequest;

HandleRequest::make(function (Context $context): ?ResponseInterface {
    if ($context->path() === 'my-extension') {
        return $context->createResponse([
            'myext:greeting' => 'Hello world!',
        ]);
    }

    return null;
});
```

### Sparse Fieldsets

The `SparseFields` hook lets your extension change which fields are serialized
for a resource. It should return the names of the fields to serialize, or `null`
to fall back to the `fields` parameter. Unknown names are ignored. Use
`array_keys($context->defaultFields($resource))` to get the names of the fields
included by default:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Extension\Hook\SparseFields;
use Tobyz\JsonApiServer\Resource\Resource;

SparseFields::make(function (Resource $resource, Context $context): ?array {
    if ($resource->type() !== 'articles') {
        return null;
    }

    return ['title'];
});
```

Its parameters are loaded on endpoints that serialize resource documents. A
custom endpoint that serializes resources can include them by adding
`HookParameters::for(SparseFields::class)` to its parameters.

### Activating Extensions

Your extension's URI is included in the response `Content-Type` header when it
has been applied, which happens automatically when a hook returns a response or
fieldset. If your extension changes the response in another way, call
`activateExtension` on the context.
