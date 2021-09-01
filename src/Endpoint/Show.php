<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Endpoint;

use JsonApiPhp\JsonApi\CompoundDocument;
use JsonApiPhp\JsonApi\Included;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsMeta;
use Tobyz\JsonApiServer\Endpoint\Concerns\IncludesData;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Serializer;

use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\run_callbacks;

class Show
{
    use IncludesData;
    use BuildsMeta;

    public function handle(Context $context, ResourceType $resourceType, $model): ResponseInterface
    {
        run_callbacks($resourceType->getSchema()->getListeners('show'), [&$model, $context]);

        $include = $this->getInclude($context, $resourceType);

        $serializer = new Serializer($context);
        $serializer->add($resourceType, $model, $include);

        [$primary, $included] = $serializer->serialize();

        return json_api_response(
            new CompoundDocument(
                $primary[0],
                new Included(...$included),
                ...$this->buildMeta($context)
            )
        );
    }
}
