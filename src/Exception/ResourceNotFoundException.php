<?php

namespace Tobscure\JsonApiServer\Exception;

use RuntimeException;

class ResourceNotFoundException extends RuntimeException
{
    protected $type;

    public function __construct(string $type, $id = null)
    {
        parent::__construct("Resource [$type".($id !== null ? ".$id" : '').'] not found.');

        $this->type = $type;
    }

    public function getStatusCode()
    {
        return 404;
    }
}
