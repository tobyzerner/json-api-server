# Creating Resources

You can allow resources to be [created](https://jsonapi.org/format/#crud-creating) using the `creatable` and `notCreatable` methods on the schema builder. 

Optionally pass a closure that returns a boolean value.

```php
$type->creatable();

$type->creatable(function (Context $context) {
    return $context->getRequest()->getAttribute('user')->isAdmin();
});
```

## Customizing the Model

When creating a resource, an empty model is supplied by the adapter. You may wish to override this and provide a custom model in special circumstances. You can do so using the `model` method:

```php
$type->model(function (Context $context) {
    return new CustomModel;
});
```

## Events

### `creating`

Run after values have been set on the model, but before it is saved.

```php
$type->creating(function (&$model, Context $context) {
    // do something
});
```

### `created`

Run after the model is saved, and before it is shown in a JSON:API document.

```php
$type->created(function (&$model, Context $context) {
    $context->meta('foo', 'bar');
});
```
