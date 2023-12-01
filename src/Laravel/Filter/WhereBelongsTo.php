<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Schema\Filter;

class WhereBelongsTo extends Filter
{
    protected ?string $relationship = null;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function relationship(?string $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        $relationship = $query->getModel()->{$this->relationship ?: $this->name}();

        if (!array_is_list($values = (array) $value)) {
            throw (new BadRequestException('filter value must be list'))->setSource([
                'parameter' => "filter[$this->name]",
            ]);
        }

        $query->whereIn(
            $relationship->getQualifiedForeignKeyName(),
            array_merge(...array_map(fn($v) => explode(',', $v), $values)),
        );
    }
}
