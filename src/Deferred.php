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

use Closure;

class Deferred
{
    private $callback;

    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    public function resolve()
    {
        return ($this->callback)();
    }
}
