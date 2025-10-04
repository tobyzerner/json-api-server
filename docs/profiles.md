# Profiles

[Profiles](https://jsonapi.org/format/1.1/#profiles) allow clients and servers
to communicate about additional semantics or constraints applied to a JSON:API
implementation. Unlike extensions, profiles can be safely ignored by the server
if they are not recognized.

## Requested Profiles

When a client includes a profile in their `Accept` header, you can check if it
was requested using the `profileRequested` method on the context:

```php
use Tobyz\JsonApiServer\Context;

if ($context->profileRequested('https://example.com/my-profile')) {
    // Client has requested this profile
    // Optionally activate profile-specific behavior
}
```

You can also get all requested profile URIs as an array:

```php
$profiles = $context->requestedProfiles();
// ['https://example.com/profile1', 'https://example.com/profile2']
```

## Activating Profiles

When you implement profile-specific behavior, you should activate the profile so
it appears in the response `Content-Type` header:

```php
if ($context->profileRequested('https://example.com/my-profile')) {
    $context->activateProfile('https://example.com/my-profile');

    // Add profile-specific data or behavior
}
```

The activated profile URIs will automatically be included in the response
`Content-Type` header:

```
Content-Type: application/vnd.api+json; profile="https://example.com/my-profile"
```
