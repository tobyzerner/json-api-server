<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Doctrine\Inflector\InflectorFactory;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\SchemaContext;

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

    protected function serializeData($value, Context $context): array
    {
        if (!$value) {
            return ['data' => null];
        }

        return ['data' => $this->serializeIdentifier($value, $context)];
    }

    public function deserializeData(mixed $data, Context $context): mixed
    {
        if ($data === null) {
            return null;
        }

        return $this->resourceForIdentifier($data, $context);
    }

    protected function getDataSchema(SchemaContext $context): array
    {
        $linkage = $this->getLinkageSchema($context);

        return $this->nullable ? ['oneOf' => [$linkage, ['type' => 'null']]] : $linkage;
    }
}
