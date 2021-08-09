# Showing Resources

For each resource type, a `GET /{type}/{id}` endpoint is exposed to show an individual resource.

## Events

### `show`

Run after the model has been retrieved, but before it is serialized into a JSON:API document.

```php
$type->show(function (&$model, Context $context) {
    // do something
});
```
