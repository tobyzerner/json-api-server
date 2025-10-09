<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\SchemaContext;

class Update extends AggregateEndpoint implements ProvidesResourceLinks, ProvidesRelationshipLinks
{
    private UpdateResource $updateResource;
    private UpdateRelationship $updateRelationship;

    public function __construct()
    {
        $this->updateResource = UpdateResource::make();
        $this->updateRelationship = UpdateRelationship::make();
    }

    public static function make(): static
    {
        return new static();
    }

    public function endpoints(): array
    {
        return [$this->updateResource, $this->updateRelationship];
    }

    public function updateResource(Closure $callback): static
    {
        $callback($this->updateResource);

        return $this;
    }

    public function updateRelationship(Closure $callback): static
    {
        $callback($this->updateRelationship);

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->updateResource->visible($condition);
        $this->updateRelationship->visible($condition);

        return $this;
    }

    public function hidden(bool|Closure $condition = true): static
    {
        $this->updateResource->hidden($condition);
        $this->updateRelationship->hidden($condition);

        return $this;
    }

    public function parameters(array $parameters): static
    {
        $this->updateResource->parameters($parameters);

        return $this;
    }

    public function response(Closure $callback): static
    {
        $this->updateResource->response($callback);

        return $this;
    }

    public function headers(array $headers): static
    {
        $this->updateResource->headers($headers);

        return $this;
    }

    public function meta(array $fields): static
    {
        $this->updateResource->meta($fields);

        return $this;
    }

    public function schema(array $schema): static
    {
        $this->updateResource->schema($schema);

        return $this;
    }

    public function description(?string $description): static
    {
        $this->updateResource->description($description);

        return $this;
    }

    public function defaultInclude(array $paths): static
    {
        $this->updateResource->defaultInclude($paths);

        return $this;
    }

    public function resourceLinks(SchemaContext $context): array
    {
        return $this->updateResource->resourceLinks($context);
    }

    public function relationshipLinks(Relationship $field, SchemaContext $context): array
    {
        return $this->updateRelationship->relationshipLinks($field, $context);
    }
}
