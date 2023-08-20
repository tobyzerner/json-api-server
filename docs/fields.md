# Fields

A resource object's attributes and relationships are collectively called its
"fields".

Fields share a common namespace with each other and with `type` and `id`. In
other words, a resource can not have an attribute and relationship with the same
name, nor can it have an attribute or relationship named `type` or `id`.

Each resource class contains a `fields` method. This method returns an array of
fields, which define the attributes and relationships of the resource.

<!-- prettier-ignore -->
```php
use Tobyz\JsonApiServer\Field;

class PostsResource extends Resource
{
    // ...

    public function fields(): array
    {
        return [
            Field\Str::make('title')->writable(),
            Field\Str::make('body')->writable(),
            Field\DateTime::make('createdAt'),
            Field\ToOne::make('author')->type('users')->includable(),
            Field\ToMany::make('comments'),
        ];
    }
}
```

Learn more about the available [Attributes](attributes.md) and
[Relationships](relationships.md).

## Visibility

You can restrict the visibility of a field using the `visible` and `hidden`
methods.

You can optionally supply a closure to these methods which will receive the
model instance and should return a boolean value.

For example, the following schema will make an email attribute that only appears
when the authenticated user is viewing their own profile:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Field\Str;

Str::make('email')->visible(
    fn($model, Context $context) => $model->id ===
        $context->request->getAttribute('userId'),
);
```

Hiding a field completely is useful when you want it the field to be available
for writing but not reading – for example, a password field:

```php
Str::make('password')
    ->hidden()
    ->writable();
```

## Reading

The fields you define on your resource will be used to serialize models into
JSON:API resource objects.

By default, the value of each field will be retrieved from the model using the
`getValue` method on your resource. A basic implementation is provided for you
in the base `Resource` class:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Field\Field;

class Resource
{
    public function getValue(
        object $model,
        Field $field,
        Context $context,
    ): mixed {
        return $model->{$field->property ?: $field->name} ?? null;
    }
}
```

You can override this with an implementation specific to your application's
model layer.

### Properties

In the default implementation of `getValue`, the value is retrieved from the
model using the field's `property` if it is set, otherwise falling back to the
field's `name`.

To configure which property a field represents, use the `property` method:

```php
Str::make('firstName'); // Reads from $model->firstName
Str::make('firstName')->property('fname'); // Reads from $model->fname
```

### Getters

You can define custom retrieval logic for a specific field using the `get`
method. This will be used to retrieve the value from the model instead of the
resource's `getValue` method:

```php
Str::make('firstName')->get(
    fn($model, Context $context) => $model->getFirstName(),
);
```

### Serialization

Once a value has been retrieved, if you would like to perform any conversion
before it appears in the JSON output, you can use the `serialize` method:

```php
Str::make('firstName')->serialize(
    fn($value, Context $context) => ucfirst($value),
);
```

### Deferred Values

Sometimes you may need to defer retrieving the value of a field until after all
other fields have been processed. For example, if a relationship needs to be
loaded from the database, it might be best to wait until you know all the models
that it needs to be loaded for so that it can be done in a single database
query.

In these cases, you can return a closure from your resource's `getValue` method,
or a field's getter, to be evaluated at the end of serialization:

```php
use Tobyz\JsonApiServer\Schema\Field\Relationship;

class PostsResource extends Resource
{
    // ...

    public function getValue(
        object $model,
        Field $field,
        Context $context,
    ): mixed {
        if ($field instanceof Relationship) {
            Buffer::add($model, $field);

            return fn() => Buffer::load($model, $field, $context);
        }

        return parent::getValue($model, $field, $context);
    }
}
```

In the above example, the exact implementation of `Buffer` is up to you. The
concept is that every post being serialized will be added to the buffer; then,
when the first deferred value is evaluated, the buffer can load the relationship
for all of the buffered posts at once.

## Writing

By default, fields are read-only. You can allow a field to be written to in the
[Create](create.md) and [Update](update.md) endpoints using the `writable` and
`readonly` methods.

You can optionally supply a closure to these methods which will receive the
model instance, and should return a boolean value.

For example, the following schema will make an email attribute that is only
writable by the self:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Field\Str;

Str::make('email')->writable(
    fn($model, Context $context) => $model->id ===
        $context->request->getAttribute('userId'),
);
```

If you only want the field to be writable when a resource is being created, but
not when an existing resource is being updated, you may use the
`writableOnCreate` method:

```php
Str::make('email')->writableOnCreate();
```

### Default Values

If you would like to provide a default value to be used when creating a new
resource if there is no value provided in the request, you can pass a closure to
the `default` method:

```php
use Tobyz\JsonApiServer\Field\DateTime;

DateTime::make('joinedAt')->default(fn() => new \DateTime());
```

### Required

By default, writable fields are optional – they can be omitted when creating a
new resource and the server will not complain.

If you would like to mark a writable field as required, so that it must be
provided when creating a new resource, you can use the `required` method:

```php
Str::make('email')
    ->writable()
    ->required();
```

### Nullable

By default, writable fields are non-nullable – they cannot be set to `null`. If
you would like to allow `null` as a valid value for a field, you can use the
`nullable` method:

```php
Str::make('color')
    ->writable()
    ->nullable();
```

### Deserialization

If you want to perform any conversion on the data provided for a field before it
is validated and saved, you can use the `deserialize` method:

```php
Str::make('firstName')->deserialize(
    fn($value, Context $context) => ucfirst($value),
);
```

### Validation

You can assert that the deserialized value provided for a field is valid before
it is saved to the model. Provide a closure to the `validate` method, and call
the `$fail` argument if validation fails:

```php
Str::make('email')->validate(function (
    $value,
    callable $fail,
    Context $context,
) {
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $fail('Invalid email');
    }
});
```

::: tip Laravel Integration  
You can use Laravel's [Validation](https://laravel.com/docs/8.x/validation)
component for field validation with the
[`rules` helper function](laravel.md#rules).  
:::

### Setters

By default, the value provided for each field will be set to the model using the
`setValue` method on your resource (which must be implemented as part of the
[Create](create.md) and [Update](update.md) endpoints).

If you would like to define custom hydration logic for a specific field, use the
`set` method:

```php
Str::make('name')->set(function ($model, $value, Context $context) {
    $model->first_name = explode(' ', $value)[0];
    $model->last_name = explode(' ', $value)[1];
});
```

### Savers

If you need complete control over how your field is persisted, you can use the
`save` method to provide a closure that will be run **after** your model has
been saved. If specified, the resource's `setValue` method will **not** be
called for the field.

```php
Str::attribute('locale')->save(function ($model, $value, Context $context) {
    $model
        ->preferences()
        ->where('key', 'locale')
        ->update(['value' => $value]);
});
```
