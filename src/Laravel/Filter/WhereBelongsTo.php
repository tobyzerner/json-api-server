<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Illuminate\Contracts\Database\Query\Expression;
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

        $this->type(Arr::make()->items(Str::make())->commaSeparated());
    }

    public function relationship(?string $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        if ($this->asBoolean) {
            $query->{$value ? 'whereNotNull' : 'whereNull'}($this->getColumn($query));
            return;
        }

        parent::applyValue($query, $value, $context);
    }

    protected function getColumn(object $query): string|Expression
    {
        if ($this->column) {
            return $this->column;
        }

        $relationship = $query->getModel()->{$this->relationship ?: $this->name}();

        return $relationship->getQualifiedForeignKeyName();
    }
}
