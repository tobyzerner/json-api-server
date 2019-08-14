<?php

namespace Tobyz\JsonApiServer\Exception;

use JsonApiPhp\JsonApi\Error;
use Tobyz\JsonApiServer\ErrorProviderInterface;

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
                new Error\Status($this->getJsonApiStatus()),
                new Error\Detail($this->getMessage())
            )
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '404';
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
