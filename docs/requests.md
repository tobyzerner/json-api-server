# Handling Requests

The `JsonApi` class is a
[PSR-15 request handler](https://www.php-fig.org/psr/psr-15/).

Instantiate it with a **base path or URI**, then pass in a PSR-7 request and get
back a PSR-7 response. Catch any exceptions and pass them back into the `error`
method to generate a JSON:API error document:

```php
use Tobyz\JsonApiServer\JsonApi;

$api = new JsonApi('/api');

/** @var Psr\Http\Message\ServerRequestInterface $request */
/** @var Psr\Http\Message\ResponseInterface $response */
try {
    $response = $api->handle($request);
} catch (Throwable $e) {
    $response = $api->error($e);
}
```

## Authentication

You (or your framework) are responsible for performing authentication.

Often you will need to access information about the authenticated user inside of
your resource definition. An effective way to pass on this information is by
setting an attribute on your Request object before passing it into the request
handler.

```php
$request = $request->withAttribute('user', $user);
```

You can then retrieve the value from the request via the
[Context object](context.md) that is passed into many callbacks:

```php
use Tobyz\JsonApiServer\Context;

Attribute::make('email')->visible(
    fn($model, Context $context) => $model->id ===
        $context->request->getAttribute('user')->id,
);
```
