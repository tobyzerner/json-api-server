# Error Handling

The `JsonApi` class can produce [JSON:API error responses](https://jsonapi.org/format/#errors) from exceptions.

This is achieved by passing the caught exception into the `error` method.

```php
try {
    $response = $api->handle($request);
} catch (Exception $e) {
    $response = $api->error($e);
}
```

## Error Providers

Exceptions can implement the `ErrorProviderInterface` to determine what status code will be used in the response, and any JSON:API error objects to be rendered in the document.

The interface defines two methods:

* `getJsonApiStatus` which must return a string.
* `getJsonApiErrors` which must return an array of JSON-serializable content, such as [json-api-php](https://github.com/json-api-php/json-api) error objects

```php
use JsonApiPhp\JsonApi\Error;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class ImATeapotException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            new Error(
                new Error\Title("I'm a teapot"),
                new Error\Status($this->getJsonApiStatus())
            )
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '418';
    }
}
```

Exceptions that do not implement this interface will result in a generic `500 Internal Server Error` response.
