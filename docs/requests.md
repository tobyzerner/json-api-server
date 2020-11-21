# Handling Requests

The `JsonApi` class is a [PSR-15 request handler](https://www.php-fig.org/psr/psr-15/).

Instantiate it with your **API's base path**, then pass in a PSR-7 request and you'll get back a PSR-7 response. You should catch any exceptions and pass them back into the `error` method to generate a JSON:API error document.
 
```php
use Tobyz\JsonApiServer\JsonApi;

$api = new JsonApi('/api');

/** @var Psr\Http\Message\ServerRequestInterface $request */
/** @var Psr\Http\Message\ResponseInterface $response */
try {
    $response = $api->handle($request);
} catch (Exception $e) {
    $response = $api->error($e);
}
```

::: tip
In Laravel, you'll need to [convert the Laravel request into a PSR-7 request](https://laravel.com/docs/8.x/requests#psr7-requests) before you can pass it into `JsonApi`. You can then return the response directly from the route or controller – the framework will automatically convert it back into a Laravel response and display it.
:::

## Authentication

You (or your framework) are responsible for performing authentication.

Often you will need to access information about the authenticated user inside of your schema – for example, when [scoping](scopes) which resources a visible within the API. An effective way to pass on this information is by setting an attribute on your Request object before passing it into the request handler.

```php
$request = $request->withAttribute('user', $user);
```
