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

use JsonApiPhp\JsonApi\Meta;
use JsonApiPhp\JsonApi\MetaDocument;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsMeta;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\ResourceType;

use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\run_callbacks;

class Delete
{
    use BuildsMeta;

    /**
     * @throws ForbiddenException if the resource is not deletable.
     */
    public function handle(Context $context, ResourceType $resourceType, $model): ResponseInterface
    {
        $schema = $resourceType->getSchema();

        if (! evaluate($schema->isDeletable(), [$model, $context])) {
            throw new ForbiddenException(sprintf(
                'Cannot delete resource %s:%s',
                $resourceType->getType(),
                $resourceType->getAdapter()->getId($model)
            ));
        }

        run_callbacks($schema->getListeners('deleting'), [&$model, $context]);

        if ($deleteCallback = $schema->getDeleteCallback()) {
            $deleteCallback($model, $context);
        } else {
            $resourceType->getAdapter()->delete($model);
        }

        run_callbacks($schema->getListeners('deleted'), [&$model, $context]);

        if (count($meta = $this->buildMeta($context))) {
            $meta[] = $this->buildJsonApiObject($context);

            return json_api_response(
                new MetaDocument(...$meta)
            );
        }

        return new Response(204);
    }
}
