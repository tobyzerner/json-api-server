<?php

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\SchemaContext;

class Link extends Field
{
    public bool $object = false;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public static function location(): ?string
    {
        return null;
    }

    public function object(bool $object = true): static
    {
        $this->object = $object;

        return $this;
    }

    public function getSchema(SchemaContext $context): array
    {
        return parent::getSchema($context) +
            ($this->object
                ? ['$ref' => '#/components/schemas/jsonApiLinkObject']
                : ['type' => 'string', 'format' => 'uri']);
    }
}
