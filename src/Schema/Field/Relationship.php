<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;

abstract class Relationship extends Field
{
    use HasMeta;
    use FindsResources;

    public array $collections;
    public bool $includable = false;
    public bool $linkage = false;

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
     * Include linkage for this relationship.
     */
    public function withLinkage(): static
    {
        $this->linkage = true;

        return $this;
    }

    /**
     * Don't include linkage for this relationship.
     */
    public function withoutLinkage(): static
    {
        $this->linkage = false;

        return $this;
    }

    public function getValue(Context $context): mixed
    {
        if ($context->include === null && !$this->linkage) {
            return null;
        }

        return parent::getValue($context);
    }

    protected function getRelatedResources(JsonApi $api): array
    {
        return array_merge(
            ...array_map(
                fn($collection) => isset($api->collections[$collection])
                    ? $api->getCollection($collection)->resources()
                    : [],
                $this->collections,
            ),
        );
    }

    protected function findResourceForIdentifier(array $identifier, Context $context): mixed
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

    public function getSchema(JsonApi $api): array
    {
        return ['nullable' => false] +
            parent::getSchema($api) + [
                'type' => 'object',
                'properties' => ['data' => $this->getDataSchema($api)],
                'required' => $this->required ? ['data'] : [],
            ];
    }

    abstract protected function getDataSchema(JsonApi $api): array;
}
