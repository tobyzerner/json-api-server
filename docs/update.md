# Updating Resources

You can allow resources to be [updated](https://jsonapi.org/format/#crud-updating) using the `updatable` and `notUpdatable` methods on the schema builder. 

Optionally pass a closure that returns a boolean value.

```php
$type->updatable();

$type->updatable(function (Context $context) {
    return $context->getRequest()->getAttribute('user')->isAdmin();
});
```

## Events

### `updating`

Run after values have been set on the model, but before it is saved.

```php
$type->updating(function (&$model, Context $context) {
    // do something
});
```

### `updated`

Run after the model is saved, and before it is shown in a JSON:API document.

```php
$type->updated(function (&$model, Context $context) {
    // do something
});
```
