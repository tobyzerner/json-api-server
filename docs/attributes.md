# Attributes

## Generic Attributes

You can define a generic attribute on your resource using the `Attribute` class.
No specific serialization, deserialization, or validation will be performed:

```php
use Tobyz\JsonApiServer\Field\Attribute;

Attribute::make('title');
```

## Typed Attributes

json-api-server includes a selection of typed attribute implementations to match
the data types in the
[OpenAPI specification](https://swagger.io/docs/specification/data-models/data-types/).

### Boolean

The `Boolean` attribute serializes values to booleans, and performs validation
to ensure that incoming values are booleans.

```php
use Tobyz\JsonApiServer\Field\Boolean;

Boolean::make('active');
```

### Date and DateTime

The `Date` and `DateTime` attributes serialize and deserialize values between
strings using RFC 3339 notation and `DateTime` objects, and perform validation
to ensure that incoming values match this format.

```php
use Tobyz\JsonApiServer\Field\Date;
use Tobyz\JsonApiServer\Field\DateTime;

Date::make('dob');
DateTime::make('publishedAt');
```

### BooleanDateTime

The `BooleanDateTime` attribute behaves like the `Boolean` attribute, except
that before setting the value to the model, a `true` value is set as the current
date, and a `false` value is set as `null`. This can be used to represent a
field that is stored internally as a timestamp as a boolean in the API.

```php
use Tobyz\JsonApiServer\Field\DateTime;

BooleanDateTime::make('isDeleted')
    ->property('deleted_at')
    ->writable();
```

### Number and Integer

The `Number` attribute serializes values to floats, and performs validation to
ensure that incoming values are numeric.

```php
use Tobyz\JsonApiServer\Field\Number;

Number::make('weight');
```

The `Integer` attribute serializes values to integers, and performs validation
to ensure that incoming values are integers.

```php
use Tobyz\JsonApiServer\Field\Integer;

Integer::make('commentCount');
```

#### Minimum and Maximum

Use the `minimum` and `maximum` methods to specify the range of possible values.

```php
use Tobyz\JsonApiServer\Field\Number;

Number::make('number')
    ->minimum(1)
    ->maximum(20);
```

By default, these values are included in the range. To exclude the boundary
values, you can add a second argument:

```php
use Tobyz\JsonApiServer\Field\Number;

Number::make('number')
    ->minimum(1, exclusive: true)
    ->maximum(20, exclusive: true);
```

#### Multiples

Use the `multipleOf` method to specify that a number must be the multiple of
another number:

```php
use Tobyz\JsonApiServer\Field\Integer;

Integer::make('number')->multipleOf(10);
```

### String

The `Str` attribute serializes values to strings, and performs validation to
ensure that incoming values are strings.

```php
use Tobyz\JsonApiServer\Field\Str;

Str::make('name');
```

#### `minLength` and `maxLength`

String length can be restricted using the `minLength` and `maxLength` methods:

```php
Str::make('name')
    ->minLength(3)
    ->maxLength(20);
```

#### `enum`

You can restrict the string to a set of possible values using the `enum` method.

```php
Str::make('status')
    ->enum(['to do', 'doing', 'done']);
```

#### `pattern`

You can also validate the string against a regular expression using the
`pattern` method. Note that regular expressions should not contain delimiters,
and are case-sensitive.

```php
Str::make('ssn')->pattern('^\d{3}-\d{2}-\d{4}$');
```

#### `format`

You can mark strings with a
[format](https://swagger.io/docs/specification/data-models/data-types/#format)
to serve as a hint in your OpenAPI definition:

```php
Str::make('email')->format('email');
```

Note that this will not add any additional behaviour (like serialization and
validation) to the field – you will need to implement this yourself. For the
`date` and `date-time` formats, you should use the
[Date and DateTime](#date-and-datetime) attributes instead.
