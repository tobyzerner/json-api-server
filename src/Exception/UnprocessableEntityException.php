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

class UnprocessableEntityException extends DomainException implements ErrorProviderInterface
{
    private $failures;

    public function __construct(array $failures)
    {
        parent::__construct();

        $this->failures = $failures;
    }

    public function getJsonApiErrors(): array
    {
        return array_map(function ($failure) {
            $members = [
                new Error\Status($this->getJsonApiStatus()),
            ];

            if ($field = $failure['field'] ?? null) {
                $members[] = new Error\SourcePointer('/data/'.$field->getLocation().'/'.$field->getName());
            }

            if ($failure['message']) {
                $members[] = new Error\Detail($failure['message']);
            }

            return new Error(...$members);
        }, $this->failures);
    }

    public function getJsonApiStatus(): string
    {
        return '422';
    }
}
