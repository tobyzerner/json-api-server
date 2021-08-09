<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

final class Sort
{
    use HasDescription;
    use HasVisibility;

    private $name;
    private $callback;

    public function __construct(string $name, callable $callback)
    {
        $this->name = $name;
        $this->callback = $callback;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }
}
