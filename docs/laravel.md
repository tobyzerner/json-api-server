# Laravel Helpers

## Validation

### `rules`

Use Laravel's [Validation component](https://laravel.com/docs/8.x/validation) as a [field validator](writing.md#validation).

```php
use Tobyz\JsonApiServer\Laravel;

$type->attribute('name')
    ->validate(Laravel\rules('required|min:3|max:20'));
```

Pass a string or array of validation rules to be applied to the value. You can also pass an array of custom messages and custom attribute names as the second and third arguments.

## Authentication

### `authenticated`

A shortcut to call `Auth::check()`.

```php
$type->creatable(Laravel\authenticated());
```

### `can`

Use Laravel's [Gate component](https://laravel.com/docs/8.x/authorization) to check if the given ability is allowed. If this is used in the context of a model (eg. `updatable`, `deletable`, or on a field), then the model will be passed to the gate check as well. 

```php
$type->updatable(Laravel\can('update-post'));
```
