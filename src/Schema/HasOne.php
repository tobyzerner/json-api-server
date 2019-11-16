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

use Doctrine\Common\Inflector\Inflector;

final class HasOne extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type(Inflector::pluralize($name));
    }
}
