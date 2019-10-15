<?php

namespace Tobyz\JsonApiServer;

use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Schema\Type;

final class ResourceType
{
    private $type;
    private $adapter;
    private $schemaCallbacks = [];
    private $schema;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function adapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    public function schema(callable $callback)
    {
        $this->schemaCallbacks[] = $callback;

        return $this;
    }

    public function getSchema(): Type
    {
        if (! $this->schema) {
            $this->schema = new Type;

            run_callbacks($this->schemaCallbacks, [$this->schema]);
        }

        return $this->schema;
    }
}
