<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use JsonApiPhp\JsonApi\Error;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class BadRequestException extends DomainException implements ErrorProviderInterface
{
    private $sourceType;
    private $source;

    public function setSourceParameter(string $parameter)
    {
        $this->sourceType = 'parameter';
        $this->source = $parameter;

        return $this;
    }

    public function setSourcePointer(string $pointer)
    {
        $this->sourceType = 'pointer';
        $this->source = $pointer;

        return $this;
    }

    public function getJsonApiErrors(): array
    {
        $members = [];

        if ($this->message) {
            $members[] = new Error\Detail($this->message);
        }

        if ($this->sourceType === 'parameter') {
            $members[] = new Error\SourceParameter($this->source);
        } elseif ($this->sourceType === 'pointer') {
            $members[] = new Error\SourcePointer($this->source);
        }

        return [
            new Error(
                new Error\Title('Bad Request'),
                new Error\Status($this->getJsonApiStatus()),
                ...$members
            )
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '400';
    }
}
