<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use JsonApiPhp\JsonApi\JsonApi;
use JsonApiPhp\JsonApi\Meta;
use Tobyz\JsonApiServer\Context;

trait BuildsMeta
{
    private function buildMeta(Context $context): array
    {
        $meta = [];

        foreach ($context->getMeta() as $item) {
            $meta[] = new Meta($item->getName(), $item->getValue()($context));
        }

        return $meta;
    }

    private function buildJsonApiObject(Context $context): JsonApi
    {
        return new JsonApi('1.1');
    }
}
