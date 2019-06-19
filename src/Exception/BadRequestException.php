<?php

namespace Tobscure\JsonApiServer\Exception;

use JsonApiPhp\JsonApi\Error;
use Tobscure\JsonApiServer\ErrorProviderInterface;

class BadRequestException extends \DomainException implements ErrorProviderInterface
{
    /**
     * @var string
     */
    private $sourceParameter;

    public function __construct(string $message = '', string $sourceParameter = '')
    {
        parent::__construct($message);

        $this->sourceParameter = $sourceParameter;
    }

    public function getJsonApiErrors(): array
    {
        $members = [];

        if ($this->message) {
            $members[] = new Error\Detail($this->message);
        }

        if ($this->sourceParameter) {
            $members[] = new Error\SourceParameter($this->sourceParameter);
        }

        return [
            new Error(
                new Error\Title('Bad Request'),
                new Error\Status('400'),
                ...$members
            )
        ];
    }
}
