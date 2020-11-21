# Field Writability

By default, fields are read-only. You can allow a field to be written to in `PATCH` and `POST` requests using the `writable` and `readonly` methods.

You can optionally supply a closure to these methods which will receive the model instance, and should return a boolean value.

For example, the following schema will make an email attribute that is only writable by the self:

```php
$type->attribute('email')
    ->writable(function ($model, Request $request, Attribute $field) {
        return $model->id === $request->getAttribute('userId');
    });
```

## Writable Once

You may want a field to only be writable when creating a new resource, but not when an existing resource is being updated. This can be achieved by calling the `once` method:

```php
$type->hasOne('author')
    ->writable()->once();
```

## Default Values

You can provide a default value to be used when creating a new resource if there is no value provided by the consumer. Pass a value or a closure to the `default` method:

```php
$type->attribute('joinedAt')
    ->default(new DateTime);

$type->attribute('ipAddress')
    ->default(function (Request $request, Attribute $attribute) {
        return $request->getServerParams()['REMOTE_ADDR'] ?? null;
    });
```

::: tip
If you're using Eloquent, you could also define [default attribute values](https://laravel.com/docs/8.x/eloquent#default-attribute-values) to achieve a similar thing. However, the Request instance will not be available in this context.
:::

## Validation

You can ensure that data provided for a field is valid before the resource is saved. Provide a closure to the `validate` method, and call the first argument if validation fails:

```php
$type->attribute('email')
    ->validate(function (callable $fail, $value, $model, Request $request, Attribute $attribute) {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('Invalid email');
        }
    });
```

::: tip
You can easily use Laravel's [Validation](https://laravel.com/docs/8.x/validation) component for field validation with the [`rules` helper function](laravel.md#validation).
:::

This works for relationships, too. The related models will be retrieved via your adapter and passed into your validation function.

```php
$type->hasMany('groups')
    ->validate(function (callable $fail, array $groups, $model, Request $request, Attribute $attribute) {
        foreach ($groups as $group) {
            if ($group->id === 1) {
                $fail('You cannot assign this group');
            }
        }
    });
```

## Transformers

Use the `transform` method on an attribute to mutate any incoming value before it is saved to the model.

```php
$type->attribute('firstName')
    ->transform(function ($value, Request $request, Attribute $attribute) {
        return ucfirst($value);
    });
```

::: tip
If you're using Eloquent, you could also define attribute [casts](https://laravel.com/docs/8.x/eloquent-mutators#attribute-casting) or [mutators](https://laravel.com/docs/8.x/eloquent-mutators#defining-a-mutator) on your model to achieve a similar thing.
:::

## Setters

Use the `set` method to define custom mutation logic for your field, instead of just setting the value straight on the model property.

```php
$type->attribute('firstName')
    ->set(function ($value, $model, Request $request, Attribute $attribute) {
        $model->first_name = ucfirst($value);
        if ($model->first_name === 'Toby') {
            $model->last_name = 'Zerner';
        }
    });
```

## Savers

If your field corresponds to some other form of data storage rather than a simple property on your model, you can use the `save` method to provide a closure that will be run _after_ your model has been successfully saved. If specified, the adapter will NOT be used to set the field on the model.

```php
$type->attribute('locale')
    ->save(function ($value, $model, Request $request, Attribute $attribute) {
        $model->preferences()
            ->where('key', 'locale')
            ->update(['value' => $value]);
    });
```

## Events

### `onSaved`

Run after a field has been successfully saved.

```php
$type->attribute('email')
    ->onSaved(function ($value, $model, Request $request, Attribute $attribute) {
        event(new EmailWasChanged($model));
    });
```
