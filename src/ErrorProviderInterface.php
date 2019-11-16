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

use JsonApiPhp\JsonApi\Error;

interface ErrorProviderInterface
{
    /**
     * Get JSON:API error objects that represent this error.
     *
     * @return Error[]
     */
    public function getJsonApiErrors(): array;

    /**
     * Get the most generally applicable HTTP error code for this error.
     */
    public function getJsonApiStatus(): string;
}
