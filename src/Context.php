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


use Psr\Http\Message\ServerRequestInterface;

class Context
{
    private $api;
    private $request;
    private $resource;
    private $model;
    private $field;

    public function __construct(JsonApi $api, ResourceType $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
    }

    public function getApi(): JsonApi
    {
        return $this->api;
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function forRequest(ServerRequestInterface $request)
    {
        $new = clone $this;
        $new->request = $request;
        return $new;
    }

    public function getResource(): ?ResourceType
    {
        return $this->resource;
    }

    public function forResource(ResourceType $resource)
    {
        $new = clone $this;
        $new->resource = $resource;
        $new->model = null;
        return $new;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function forModel($model)
    {
        $new = clone $this;
        $new->model = $model;
        return $new;
    }

    public function getField(): ?Field
    {
        return $this->field;
    }

    public function forField(Field $field)
    {
        $new = clone $this;
        $new->field = $field;
        return $new;
    }
}
