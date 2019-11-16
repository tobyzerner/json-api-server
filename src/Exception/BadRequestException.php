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
