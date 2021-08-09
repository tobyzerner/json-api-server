<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Extension;

use Psr\Http\Message\ResponseInterface as Response;
use Tobyz\JsonApiServer\Context;

abstract class Extension
{
    /**
     * The URI that uniquely identifies this extension.
     *
     * @see https://jsonapi.org/format/1.1/#media-type-parameter-rules
     */
    abstract public function uri(): string;

    /**
     * Handle a request.
     */
    public function handle(Context $context): ?Response
    {
        return null;
    }
}
