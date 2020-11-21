# Field Visibility

Restrict the visibility of a field using the `visible` and `hidden` methods.

You can optionally supply a closure to these methods which will receive the model instance, and should return a boolean value.

For example, the following schema will make an email attribute that only appears when the authenticated user is viewing their own profile:

```php
$type->attribute('email')
    ->visible(function ($model, Request $request, Attribute $field) {
        return $model->id === $request->getAttribute('userId');
    });
```

Hiding a field completely is useful when you want it the field to be available for [writing](writing.md) but not reading – for example, a password field.

```php
$type->attribute('password')
    ->hidden()
    ->writable();
```
