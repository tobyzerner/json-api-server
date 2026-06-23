<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Type\Arr;
use Tobyz\JsonApiServer\Schema\Type\Str;

class WhereBelongsTo extends Where
{
    public const SUPPORTED_OPERATORS = ['eq', 'in', 'ne', 'notin', 'null', 'notnull'];

    protected ?string $relationship = null;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type(
            Arr::make()
                ->items(Str::make())
                ->commaSeparated(),
        );
    }

    public function relationship(?string $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        if ($this->asBoolean) {
            [$column, $bindings] = $this->getColumn($query, $context);

            $this->addColumnBindings($query, $bindings);
            $query->{$value ? 'whereNotNull' : 'whereNull'}($column);
            return;
        }

        parent::applyValue($query, $value, $context);
    }

    protected function getColumn(object $query, Context $context): array
    {
        if ($this->column !== null) {
            return parent::getColumn($query, $context);
        }

        $relationship = $query->getModel()->{$this->relationship ?: $this->name}();

        return [$relationship->getQualifiedForeignKeyName(), []];
    }
}
