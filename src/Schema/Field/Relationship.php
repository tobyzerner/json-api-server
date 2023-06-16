<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;

abstract class Relationship extends Field
{
    use HasMeta;
    use FindsResources;

    public array $types;
    public bool $includable = false;
    public bool $linkage = false;

    /**
     * Set the resource type that this relationship is to.
     */
    public function type(string|array $type): static
    {
        $this->types = (array) $type;

        return $this;
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

    protected function findResourceForIdentifier(array $identifier, Context $context): mixed
    {
        if (!isset($identifier['type'])) {
            throw new BadRequestException('type not specified');
        }

        if (!isset($identifier['id'])) {
            throw new BadRequestException('id not specified');
        }

        if (!in_array($identifier['type'], $this->types)) {
            throw new BadRequestException("type [{$identifier['type']}] not allowed");
        }

        $resource = $context->api->getResource($identifier['type']);

        return $this->findResource($context->withResource($resource), $identifier['id']);
    }
}
