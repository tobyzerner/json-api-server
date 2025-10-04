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
interface to determine what status code will be used in the response, and define
a JSON:API error object to be rendered in the document.

The interface defines two methods:

- `getJsonApiStatus` which must return the HTTP status code applicable to the
  exception as a string.
- `getJsonApiError` which must return a JSON:API error object.

```php
use Tobyz\JsonApiServer\Exception\ErrorProvider;

class ImATeapotException implements ErrorProvider
{
    public function getJsonApiError(): array
    {
        return [
            'title' => "I'm A Teapot",
            'status' => $this->getJsonApiStatus(),
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

### Creating Custom Exceptions

The simplest way to create custom exceptions is to extend one of the base
exception classes like `BadRequestException`, `UnprocessableEntityException`, or
`ForbiddenException`. These base classes implement `ErrorProvider` and use the
`JsonApiError` trait internally to provide automatic error formatting.

For most cases, just extend a base exception and provide a message which will be
used at the error `detail`. For more control, you can set the `$this->error`
array in your constructor, which will be merged with the defaults:

```php
use Tobyz\JsonApiServer\Exception\BadRequestException;

class ProductOutOfStockException extends BadRequestException
{
    public function __construct(string $productId)
    {
        parent::__construct("Product $productId is out of stock");

        $this->error = [
            'meta' => ['productId' => $productId],
            'links' => [
                'type' =>
                    'https://example.com/docs/errors#product_out_of_stock',
            ],
        ];
    }
}
```

This automatically generates:

- `code`: `product_out_of_stock` (derived from class name)
- `title`: `Product Out Of Stock` (derived from class name)
- `detail`: `Product ABC123 is out of stock` (from constructor message)
- `status`: `400` (inherited from BadRequestException)

#### Helper Methods

The `JsonApiError` trait also provides fluent helper methods for modifying the
error object from the context in which it is thrown:

```php
throw (new UnknownFieldException('email'))
    ->source(['pointer' => '/data/attributes/email'])
    ->meta(['suggestion' => 'Did you mean "emailAddress"?'])
    ->links(['about' => 'https://example.com/docs/fields']);
```

## Multiple Errors

When multiple validation errors occur (e.g., multiple field validation
failures), you can wrap them in `JsonApiErrorsException`.

```php
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\RequiredFieldException;
use Tobyz\JsonApiServer\Exception\InvalidFieldValueException;

throw new JsonApiErrorsException([
    new RequiredFieldException(),
    new InvalidFieldValueException('Must be a valid email address'),
])->prependSource(['pointer' => '/data/attributes/email']);
```

This will return a JSON:API error response with multiple error objects:

```json
{
    "errors": [
        {
            "status": "422",
            "code": "required_field",
            "title": "Required Field",
            "detail": "Field is required",
            "source": { "pointer": "/data/attributes/email" }
        },
        {
            "status": "422",
            "code": "invalid_field_value",
            "title": "Invalid Field Value",
            "detail": "Must be a valid email address",
            "source": { "pointer": "/data/attributes/email" }
        }
    ]
}
```

When `JsonApiErrorsException` contains multiple errors with different status
codes, it automatically determines the most generally applicable HTTP error code
to be used in the response.

## Customizing Error Messages

All built-in exceptions include sensible default English error messages, so they
work out-of-the-box without any configuration. The `code` and `title` are
automatically derived from the exception class name, and each exception provides
a default `detail` message.

You can customize error messages for each exception using the `errors()` method
on your `JsonApi` instance. You can override any part of the error object for
any exception by providing exception class names as keys.

```php
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;

$api->errors([
    MethodNotAllowedException::class => [
        'title' => 'Not Allowed',
        'detail' => 'The :method method is not allowed for this endpoint',
    ],
    ResourceNotFoundException::class => [
        'title' => 'Not Found',
        'detail' => 'Could not find :type resource with ID :id',
    ],
]);
```

### Placeholder Replacement

The `detail` property supports placeholder replacement using the `:placeholder`
syntax. Placeholders are replaced with values found in the error object's `meta`
data:

```php
ResourceNotFoundException::class => [
    'detail' => 'Could not find :type resource with ID :id',
]
```

When a `ResourceNotFoundException` is thrown with `meta` containing
`['type' => 'users', 'id' => '123']`, the detail becomes: "Could not find users
resource with ID 123"
