# Updating Resources

You can allow resources to be [updated](https://jsonapi.org/format/#crud-updating) using the `updatable` and `notUpdatable` methods on the schema builder. 

Optionally pass a closure that returns a boolean value.

```php
$type->updatable();

$type->updatable(function (Request $request) {
    return $request->getAttribute('user')->isAdmin();
});
```

## Events

### `onUpdating`

Run before the model is saved.

```php
$type->onUpdating(function ($model, Request $request) {
    // do something
});
```

### `onUpdated`

Run after the model is saved.

```php
$type->onUpdated(function ($model, Request $request) {
    // do something
});
```
