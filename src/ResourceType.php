<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer;

use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Schema\Type;

final class ResourceType
{
    private $type;
    private $adapter;
    private $buildSchema;
    private $schema;

    public function __construct(string $type, AdapterInterface $adapter, callable $buildSchema = null)
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

    public function getSchema(): Type
    {
        if (! $this->schema) {
            $this->schema = new Type;

            if ($this->buildSchema) {
                ($this->buildSchema)($this->schema);
            }
        }

        return $this->schema;
    }
}
