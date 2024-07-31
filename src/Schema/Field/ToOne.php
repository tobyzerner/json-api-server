<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Doctrine\Inflector\InflectorFactory;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\Sourceable;

class ToOne extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type(
            InflectorFactory::create()
                ->build()
                ->pluralize($name),
        );

        $this->withLinkage();
    }

    public function serializeValue($value, Context $context): mixed
    {
        $meta = $this->serializeMeta($context);

        if ($context->include === null && !$this->hasLinkage($context) && !$meta) {
            return null;
        }

        $relationship = [
            'data' => $value
                ? $context->serializer->addIncluded($this, $value, $context->include)
                : null,
        ];

        if ($meta) {
            $relationship['meta'] = $meta;
        }

        return $relationship;
    }

    public function deserializeValue(mixed $value, Context $context): mixed
    {
        if ($this->deserializer) {
            return ($this->deserializer)($value, $context);
        }

        if (!is_array($value) || !array_key_exists('data', $value)) {
            throw new BadRequestException('relationship does not include data key');
        }

        if ($value['data'] === null) {
            return null;
        }

        try {
            return $this->findResourceForIdentifier($value['data'], $context);
        } catch (Sourceable $e) {
            throw $e->prependSource(['pointer' => '/data']);
        }
    }
}
