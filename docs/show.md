# Showing Resources

For each resource type, a `GET /{type}/{id}` endpoint is exposed to show an individual resource.

## Events

### `onShow`

Run after models and relationships have been retrieved, but before they are serialized into a JSON:API document.

```php
$type->onShow(function (&$model, Context $context) {
    // do something
});
```
