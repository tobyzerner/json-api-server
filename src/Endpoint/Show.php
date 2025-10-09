<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\SchemaContext;

class Show extends AggregateEndpoint implements ProvidesResourceLinks, ProvidesRelationshipLinks
{
    private ShowResource $showResource;
    private ShowRelated $showRelated;
    private ShowRelationship $showRelationship;

    public function __construct()
    {
        $this->showResource = ShowResource::make();
        $this->showRelated = ShowRelated::make();
        $this->showRelationship = ShowRelationship::make();
    }

    public static function make(): static
    {
        return new static();
    }

    public function endpoints(): array
    {
        return [$this->showResource, $this->showRelated, $this->showRelationship];
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->showResource->visible($condition);
        $this->showRelated->visible($condition);
        $this->showRelationship->visible($condition);

        return $this;
    }

    public function hidden(bool|Closure $condition = true): static
    {
        $this->showResource->hidden($condition);
        $this->showRelated->hidden($condition);
        $this->showRelationship->hidden($condition);

        return $this;
    }

    public function response(Closure $callback): static
    {
        $this->showResource->response($callback);

        return $this;
    }

    public function headers(array $headers): static
    {
        $this->showResource->headers($headers);

        return $this;
    }

    public function meta(array $fields): static
    {
        $this->showResource->meta($fields);

        return $this;
    }

    public function schema(array $schema): static
    {
        $this->showResource->schema($schema);

        return $this;
    }

    public function description(?string $description): static
    {
        $this->showResource->description($description);

        return $this;
    }

    public function defaultInclude(array $paths): static
    {
        $this->showResource->defaultInclude($paths);

        return $this;
    }

    public function seeOther(callable $callback): static
    {
        $this->showResource->seeOther($callback);

        return $this;
    }

    public function resourceLinks(SchemaContext $context): array
    {
        return $this->showResource->resourceLinks($context);
    }

    public function relationshipLinks(Relationship $field, SchemaContext $context): array
    {
        return [
            ...$this->showRelated->relationshipLinks($field, $context),
            ...$this->showRelationship->relationshipLinks($field, $context),
        ];
    }
}
