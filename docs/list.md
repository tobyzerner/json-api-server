# Listing Resources

For each resource type, a `GET /{type}` endpoint is exposed to list resources.

If you want to restrict the ability to list a resource type, use the `listable` and `notListable` methods. You can optionally pass a closure that returns a boolean value.

```php
$type->notListable();

$type->listable(function (Context $context) {
    return $context->getRequest()->getAttribute('user')->isAdmin();
});
```

## Events

### `onListing`

Run before [scopes](scopes.md) are applied to the `$query` and results are retrieved.

```php
$type->onListing(function ($query, Context $context) {
    // do something
});
```

### `onListed`

Run after models and relationships have been retrieved, but before they are serialized into a JSON:API document.

```php
$type->onListed(function ($models, Context $context) {
    // do something
});
```
