<?php

namespace Tobscure\JsonApiServer\Exception;

use JsonApiPhp\JsonApi\Error;
use Tobscure\JsonApiServer\ErrorProviderInterface;

class ResourceNotFoundException extends \RuntimeException implements ErrorProviderInterface
{
    protected $type;
    protected $id;

    public function __construct(string $type, string $id = null)
    {
        parent::__construct(
            sprintf('Resource [%s] not found.', $type.($id !== null ? '.'.$id : ''))
        );

        $this->type = $type;
        $this->id = $id;
    }

    public function getJsonApiErrors(): array
    {
        return [
            new Error(
                new Error\Title('Resource Not Found'),
                new Error\Status('404'),
                new Error\Detail($this->getMessage())
            )
        ];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
