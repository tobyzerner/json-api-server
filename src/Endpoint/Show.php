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
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Serializer;
use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\run_callbacks;

class Show
{
    use Concerns\IncludesData;

    private $api;
    private $resource;
    private $model;

    public function __construct(JsonApi $api, ResourceType $resource, $model)
    {
        $this->api = $api;
        $this->resource = $resource;
        $this->model = $model;
    }

    public function handle(Context $context): ResponseInterface
    {
        $include = $this->getInclude($context);

        $this->loadRelationships([$this->model], $include, $context);

        run_callbacks($this->resource->getSchema()->getListeners('show'), [$this->model, $context]);

        $serializer = new Serializer($this->api, $context);
        $serializer->add($this->resource, $this->model, $include);

        return json_api_response(
            new CompoundDocument(
                $serializer->primary()[0],
                new Included(...$serializer->included())
            )
        );
    }
}
