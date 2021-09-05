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

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsMeta;
use Tobyz\JsonApiServer\Endpoint\Concerns\SavesData;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\ResourceType;

use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\run_callbacks;

class Update
{
    use SavesData;

    /**
     * @throws ForbiddenException if the resource is not updatable.
     */
    public function handle(Context $context, ResourceType $resourceType, $model): ResponseInterface
    {
        $schema = $resourceType->getSchema();

        if (! evaluate($schema->isUpdatable(), [$model, $context])) {
            throw new ForbiddenException(sprintf(
                'Cannot update resource %s:%s',
                $resourceType->getType(),
                $resourceType->getAdapter()->getId($model)
            ));
        }

        $data = $this->parseData(
            $resourceType,
            json_decode($context->getRequest()->getBody()->getContents(), true),
            $model
        );

        $this->validateFields($resourceType, $data, $model, $context);
        $this->loadRelatedResources($resourceType, $data, $context);
        $this->assertDataValid($resourceType, $data, $model, $context, false);
        $this->setValues($resourceType, $data, $model, $context);

        run_callbacks($schema->getListeners('updating'), [&$model, $context]);

        $this->save($resourceType, $data, $model, $context);

        run_callbacks($schema->getListeners('updated'), [&$model, $context]);

        return (new Show())
            ->handle($context, $resourceType, $model);
    }
}
