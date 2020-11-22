# Deleting Resources

You can allow resources to be [deleted](https://jsonapi.org/format/#crud-deleting) using the `deletable` and `notDeletable` methods on the schema builder. 

Optionally pass a closure that returns a boolean value.

```php
$type->deletable();

$type->deletable(function (Context $context) {
    return $request->getAttribute('user')->isAdmin();
});
```

## Events

### `onDeleting`

Run before the model is deleted.

```php
$type->onDeleting(function ($model, Context $context) {
    // do something
});
```

### `onDeleted`

Run after the model is deleted.

```php
$type->onDeleted(function ($model, Context $context) {
    // do something
});
```
