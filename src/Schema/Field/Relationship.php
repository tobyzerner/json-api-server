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

    public function hasLinkage(Context $context): mixed
    {
        if ($this->linkage instanceof Closure) {
            return ($this->linkage)($context);
        }

        return $this->linkage;
    }

    public function getSchema(JsonApi $api): array
    {
        return ['nullable' => false] +
            parent::getSchema($api) + [
                'type' => 'object',
                'properties' => ['data' => $this->getDataSchema($api)],
                'required' => $this->required ? ['data'] : [],
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
