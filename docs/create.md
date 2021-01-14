# Creating Resources

You can allow resources to be [created](https://jsonapi.org/format/#crud-creating) using the `creatable` and `notCreatable` methods on the schema builder. 

Optionally pass a closure that returns a boolean value.

```php
$type->creatable();

$type->creatable(function (Context $context) {
    return $request->getAttribute('user')->isAdmin();
});
```

## Customizing the Model

When creating a resource, an empty model is supplied by the adapter. You may wish to override this and provide a custom model in special circumstances. You can do so using the `newModel` method:

```php
$type->newModel(function (Context $context) {
    return new CustomModel;
});
```

## Events

### `onCreating`

Run before the model is saved.

```php
$type->onCreating(function (&$model, Context $context) {
    // do something
});
```

### `onCreated`

Run after the model is saved.

```php
$type->onCreated(function (&$model, Context $context) {
    $context->meta('foo', 'bar');
});
```
