<?php

namespace Tobyz\JsonApiServer;

use Closure;
use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Schema\Builder;

class ResourceType
{
    protected $type;
    protected $adapter;
    protected $buildSchema;
    protected $schema;

    public function __construct(string $type, AdapterInterface $adapter, Closure $buildSchema = null)
    {
        $this->type = $type;
        $this->adapter = $adapter;
        $this->buildSchema = $buildSchema;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    public function getSchema(): Builder
    {
        if (! $this->schema) {
            $this->schema = new Builder;

            if ($this->buildSchema) {
                ($this->buildSchema)($this->schema);
            }
        }

        return $this->schema;
    }
}
