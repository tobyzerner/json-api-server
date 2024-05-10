<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\Sourceable;

class ToMany extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type($name);
    }

    public function serializeValue($value, Context $context): mixed
    {
        $meta = $this->serializeMeta($context);

        if ((($context->include === null && !$this->hasLinkage($context)) || $value === null) && !$meta) {
            return null;
        }

        $relationship = [];

        if ($value !== null) {
            $relationship['data'] = array_map(
                fn($model) => $context->serializer->addIncluded($this, $model, $context->include),
                $value,
            );
        }

        if ($meta) {
            $relationship['meta'] = $meta;
        }

        return $relationship;
    }

    public function deserializeValue(mixed $value, Context $context): mixed
    {
        if (!is_array($value) || !array_key_exists('data', $value)) {
            throw new BadRequestException('relationship does not include data key');
        }

        if (!array_is_list($value['data'])) {
            throw (new BadRequestException(
                'relationship data must be a list of identifier objects',
            ))->setSource(['pointer' => '/data']);
        }

        $models = [];

        foreach ($value['data'] as $i => $identifier) {
            try {
                $models[] = $this->findResourceForIdentifier($identifier, $context);
            } catch (Sourceable $e) {
                throw $e->prependSource(['pointer' => "/data/$i"]);
            }
        }

        if ($this->deserializer) {
            return ($this->deserializer)($models, $context);
        }

        return $models;
    }
}
