# Attributes

You can define a attribute field on your resource using the `Attribute` class.

```php
use Tobyz\JsonApiServer\Schema\Field\Attribute;

Attribute::make('title');
```

## Attribute Types

Attributes can be configured with a type to automatically perform appropriate
serialization, deserialization, and validation. json-api-server includes a
selection of type implementations to match the data types in the
[OpenAPI specification](https://swagger.io/docs/specification/data-models/data-types/).

### Boolean

The `Boolean` type serializes values to booleans, and performs validation to
ensure that incoming values are booleans.

```php
use Tobyz\JsonApiServer\Schema\Type\Boolean;

Attribute::make('active')->type(Boolean::make());
```

### Date and DateTime

The `Date` and `DateTime` types serialize and deserialize values between strings
using RFC 3339 notation and `DateTime` objects, and perform validation to ensure
that incoming values match this format.

```php
use Tobyz\JsonApiServer\Schema\Type\Date;
use Tobyz\JsonApiServer\Schema\Type\DateTime;

Attribute::make('dob')->type(Date::make());
Attribute::make('publishedAt')->type(DateTime::make());
```

### Number and Integer

The `Number` type serializes values to floats, and performs validation to ensure
that incoming values are numeric.

```php
use Tobyz\JsonApiServer\Schema\Type\Number;

Attribute::make('weight')->type(Number::make());
```

The `Integer` type serializes values to integers, and performs validation to
ensure that incoming values are integers.

```php
use Tobyz\JsonApiServer\Schema\Type\Integer;

Attribute::make('commentCount')->type(Integer::make());
```

#### Minimum and Maximum

Use the `minimum` and `maximum` methods to specify the range of possible values.

```php
use Tobyz\JsonApiServer\Schema\Type\Number;

Attribute::make('number')->type(
    Number::make()
        ->minimum(1)
        ->maximum(20),
);
```

By default, these values are included in the range. To exclude the boundary
values, you can add a second argument:

```php
use Tobyz\JsonApiServer\Schema\Type\Number;

Attribute::make('number')->type(
    Number::make()
        ->minimum(1, exclusive: true)
        ->maximum(20, exclusive: true),
);
```

#### Multiples

Use the `multipleOf` method to specify that a number must be the multiple of
another number:

```php
use Tobyz\JsonApiServer\Schema\Type\Integer;

Attribute::make('number')->type(Integer::make()->multipleOf(10));
```

### String

The `Str` type serializes values to strings, and performs validation to ensure
that incoming values are strings.

```php
use Tobyz\JsonApiServer\Schema\Type\Str;

Attribute::make('name')->type(Str::make());
```

#### `minLength` and `maxLength`

String length can be restricted using the `minLength` and `maxLength` methods:

```php
Attribute::make('name')->type(
    Str::make()
        ->minLength(3)
        ->maxLength(20),
);
```

#### `enum`

You can restrict the string to a set of possible values using the `enum` method.

```php
Attribute::make('status')->type(Str::make()->enum(['to do', 'doing', 'done']));
```

#### `pattern`

You can also validate the string against a regular expression using the
`pattern` method. Note that regular expressions should not contain delimiters,
and are case-sensitive.

```php
Attribute::make('ssn')->type(Str::make()->pattern('^\d{3}-\d{2}-\d{4}$'));
```

#### `format`

You can mark strings with a
[format](https://swagger.io/docs/specification/data-models/data-types/#format)
to serve as a hint in your OpenAPI definition:

```php
Attribute::make('email')->type(Str::make()->format('email'));
```

Note that this will not add any additional behaviour (like serialization and
validation) to the field – you will need to implement this yourself. For the
`date` and `date-time` formats, you should use the
[Date and DateTime](#date-and-datetime) types instead.

## Special Attributes

### BooleanDateTime

The `BooleanDateTime` attribute subclass behaves like a `Boolean`-typed
attribute, except that before setting the value to the model, a `true` value is
set as the current date, and a `false` value is set as `null`. This can be used
to represent a field that is stored internally as a timestamp as a boolean in
the API.

```php
use Tobyz\JsonApiServer\Schema\Field\BooleanDateTime;

BooleanDateTime::make('isDeleted')
    ->property('deleted_at')
    ->writable();
```
