# Error Handling

The API server can produce
[JSON:API error responses](https://jsonapi.org/format/#errors) from exceptions.

This is achieved by passing the caught exception into the `error` method.

```php
try {
    $response = $api->handle($request);
} catch (Throwable $e) {
    $response = $api->error($e);
}
```

## Error Providers

Exceptions can implement the `Tobyz\JsonApiServer\Exception\ErrorProvider`
interface to determine what status code will be used in the response, and any
JSON:API error objects to be rendered in the document.

The interface defines two methods:

-   `getJsonApiStatus` which must return a string.
-   `getJsonApiErrors` which must return an array of JSON:API error objects.

```php
use JsonApiPhp\JsonApi\Error;
use Tobyz\JsonApiServer\Exception\ErrorProvider;

class ImATeapotException implements ErrorProvider
{
    public function getJsonApiErrors(): array
    {
        return [
            [
                'title' => "I'm a teapot",
                'status' => $this->getJsonApiStatus(),
            ],
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '418';
    }
}
```

Exceptions that do not implement this interface will result in a generic
`500 Internal Server Error` response.
