# Localization

json-api-server includes a basic localization system that allows you to
customize error messages in your API responses.

## Default Language

By default, the server uses English for all error messages. These are defined in
the `Tobyz\JsonApi\Translation\EnglishCatalogue` class.

## Customizing Messages

You can customize messages in two ways:

### Merging Custom Messages

To override specific messages while keeping the defaults:

```php
$api = new JsonApi();

$api->messages([
    'resource.not_found' => 'The requested resource could not be found',
    'pagination.size_exceeded' => 'The requested page size is too large',
]);
```

This will merge your custom messages with the default English messages.

### Using a Custom Translator

For complete control over localization, you can provide your own translator
implementation:

```php
use Tobyz\JsonApiServer\Translation\TranslatorInterface;

class CustomTranslator implements TranslatorInterface
{
    public function translate(string $key, array $replacements = []): string
    {
        // Your custom translation logic here
        return $translation;
    }
}

$api = new JsonApi();
$api->setTranslator(new CustomTranslator());
```

## Message Keys

Messages support placeholder replacements using the `:placeholder` syntax. The
replacement values are passed as an array:

```php
$context->translate('resource.not_found', ['identifier' => 'users']);
// Results in: "Resource not found: users"
```

## Using Localization in Custom Code

You can access the localization system in your custom code through the Context
object:

```php
use Tobyz\JsonApiServer\Context;

$message = $context->translate('custom.key', ['name' => 'value']);
```
