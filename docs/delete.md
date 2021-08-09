# Deleting Resources

You can allow resources to be [deleted](https://jsonapi.org/format/#crud-deleting) using the `deletable` and `notDeletable` methods on the schema builder. 

Optionally pass a closure that returns a boolean value.

```php
$type->deletable();

$type->deletable(function (Context $context) {
    return $context->getRequest()->getAttribute('user')->isAdmin();
});
```

## Events

### `deleting`

Run before the model is deleted.

```php
$type->deleting(function (&$model, Context $context) {
    // do something
});
```

### `deleted`

Run after the model is deleted.

```php
$type->deleted(function (&$model, Context $context) {
    // do something
});
```
