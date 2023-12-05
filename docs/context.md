# Context

A Context object is passed into callbacks throughout your API resource
definitions.

This object contains a lot of properties and methods that may be useful:

```php
namespace Tobyz\JsonApiServer;

class Context
{
    // The API server instance
    public JsonApi $api;

    // The request that is currently being handled
    public ServerRequestInterface $request;

    // The collection that the request is for
    public ?Collection $collection = null;

    // The resource that the request is for
    public ?Resource $resource = null;

    // The endpoint handling the request
    public ?Endpoint $endpoint = null;

    // The query being constructed by the Index endpoint
    public ?object $query = null;

    // The serializer instance
    public ?Serializer $serializer = null;

    // The model that is currently being serialized, updated, or deleted
    public mixed $model = null;

    // The field that is currently being processed
    public ?Field $field = null;

    // If a relationship is being serialized, any child relationships
    // that are included
    public ?array $include = null;

    // Get the request method
    public function method(): string;

    // Get the request path relative to the API base path
    public function path(): string;

    // Get the value of a query param
    public function queryParam(string $name, $default = null): mixed;

    // Get the parsed JSON:API payload
    public function body(): ?array;

    // Get a resource by type
    public function resource(string $type): Resource;

    // Get the fields for the given resource, keyed by name
    public function fields(Resource $resource): array;

    // Get only the requested fields for the given resource
    public function sparseFields(Resource $resource): array;

    // Determine whether a field has been requested in a sparse fieldset
    public function fieldRequested(string $type, string $field, bool $default = true): bool;

    // Determine whether a sort field has been requested
    public function sortRequested(string $field): bool;
}
```
