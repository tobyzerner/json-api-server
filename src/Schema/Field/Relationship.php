<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\RelationshipEndpoint;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;

abstract class Relationship extends Field
{
    use HasMeta;
    use FindsResources;

    public array $collections;
    public bool $includable = false;
    public bool|Closure $linkage = false;
    public array $linkageMeta = [];

    public static function make(string $name): static
    {
        return new static($name);
    }

    public static function location(): ?string
    {
        return 'relationships';
    }

    /**
     * Set the collection(s) that this relationship is to.
     */
    public function collection(string|array $type): static
    {
        $this->collections = (array) $type;

        return $this;
    }

    /**
     * Set the collection(s) that this relationship is to.
     */
    public function type(string|array $type): static
    {
        return $this->collection($type);
    }

    /**
     * Allow this relationship to be included.
     */
    public function includable(): static
    {
        $this->includable = true;

        return $this;
    }

    /**
     * Don't allow this relationship to be included.
     */
    public function notIncludable(): static
    {
        $this->includable = false;

        return $this;
    }

    /**
     * Include linkage for this relationship.
     */
    public function withLinkage(bool|Closure $condition = true): static
    {
        $this->linkage = $condition;

        return $this;
    }

    /**
     * Don't include linkage for this relationship.
     */
    public function withoutLinkage(): static
    {
        return $this->withLinkage(false);
    }

    /**
     * Define meta fields for resource identifier objects in linkage.
     */
    public function linkageMeta(array $fields): static
    {
        $this->linkageMeta = array_merge($this->linkageMeta, $fields);

        return $this;
    }

    public function getValue(Context $context): mixed
    {
        if ($context->include === null && !$this->hasLinkage($context)) {
            return null;
        }

        return parent::getValue($context);
    }

    public function serializeValue($value, Context $context): mixed
    {
        $relationship =
            $context->include !== null || $this->hasLinkage($context)
                ? $this->serializeData($value, $context)
                : [];

        if ($meta = $this->serializeMeta($context)) {
            $relationship['meta'] = $meta;
        }

        $links = [];
        foreach ($context->collection->endpoints() as $endpoint) {
            if ($endpoint instanceof RelationshipEndpoint) {
                $links += $endpoint->relationshipLinks($context->model, $this, $context);
            }
        }

        if ($links) {
            $relationship['links'] = $links;
        }

        return $relationship;
    }

    abstract protected function serializeData($value, Context $context): array;

    protected function serializeIdentifier($model, Context $context): array
    {
        $context = $context->forModel($this->collections, $model);

        $identifier = $context->serializer->addIncluded($context);

        if ($this->linkageMeta && ($meta = $this->serializeLinkageMeta($context))) {
            $identifier['meta'] = $meta;
        }

        return $identifier;
    }

    protected function serializeLinkageMeta(Context $context): array
    {
        $meta = [];

        foreach ($this->linkageMeta as $field) {
            if (!$field->isVisible($context)) {
                continue;
            }

            $value = $field->getValue($context);

            $meta[$field->name] = $field->serializeValue($value, $context);
        }

        return $meta;
    }

    public function hasLinkage(Context $context): mixed
    {
        if ($this->linkage instanceof Closure) {
            return ($this->linkage)($context);
        }

        return $this->linkage;
    }

    public function getSchema(JsonApi $api): array
    {
        $schema = parent::getSchema($api);

        unset($schema['nullable']);

        if ($this->required) {
            $schema['required'] = ['data'];
        }

        return $schema + [
            'type' => 'object',
            'properties' => ['data' => $this->getDataSchema($api)],
        ];
    }

    protected function resourceForIdentifier(array $identifier, Context $context): mixed
    {
        if (!isset($identifier['type'])) {
            throw new BadRequestException('type not specified');
        }

        if (!isset($identifier['id'])) {
            throw new BadRequestException('id not specified');
        }

        $resources = $this->getRelatedResources($context->api);

        if (in_array($identifier['type'], $resources)) {
            return $this->findResource(
                $context->withCollection($context->resource($identifier['type'])),
                $identifier['id'],
            );
        }

        throw (new BadRequestException("type [{$identifier['type']}] not allowed"))->setSource([
            'pointer' => '/type',
        ]);
    }

    protected function getRelatedResources(JsonApi $api): array
    {
        return array_merge(
            ...array_map(
                fn($collection) => $api->getCollection($collection)->resources(),
                $this->collections,
            ),
        );
    }

    abstract protected function getDataSchema(JsonApi $api): array;
}
