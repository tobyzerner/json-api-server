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

final class Meta
{
    use HasDescription;

    private $name;
    private $value;

    public function __construct(string $name, callable $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): callable
    {
        return $this->value;
    }
}
