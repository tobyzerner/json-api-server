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

use JsonApiPhp\JsonApi\Error;
use RuntimeException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class UnsupportedMediaTypeException extends RuntimeException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            new Error(
                new Error\Title('Unsupported Media Type'),
                new Error\Status($this->getJsonApiStatus())
            )
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '415';
    }
}
