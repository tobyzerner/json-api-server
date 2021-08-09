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

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema\Relationship;

trait IncludesData
{
    private function getInclude(Context $context, ResourceType $resourceType): array
    {
        $queryParams = $context->getRequest()->getQueryParams();

        if (! empty($queryParams['include'])) {
            $include = $this->parseInclude($queryParams['include']);

            $this->validateInclude($context, [$resourceType], $include);

            return $include;
        }

        return [];
    }

    private function parseInclude($include): array
    {
        $tree = [];

        foreach (is_array($include) ? $include : explode(',', $include) as $path) {
            $array = &$tree;

            foreach (explode('.', $path) as $key) {
                if (! isset($array[$key])) {
                    $array[$key] = [];
                }

                $array = &$array[$key];
            }
        }

        return $tree;
    }

    private function validateInclude(Context $context, array $resourceTypes, array $include, string $path = '')
    {
        foreach ($include as $name => $nested) {
            foreach ($resourceTypes as $resource) {
                $fields = $resource->getSchema()->getFields();

                if (
                    ! isset($fields[$name])
                    || ! $fields[$name] instanceof Relationship
                    || ! $fields[$name]->isIncludable()
                ) {
                    continue;
                }

                $type = $fields[$name]->getType();

                if (is_string($type)) {
                    $relatedResource = $context->getApi()->getResourceType($type);

                    $this->validateInclude($context, [$relatedResource], $nested, $name.'.');
                } else {
                    $relatedResources = is_array($type) ? array_map(function ($type) use ($context) {
                        return $context->getApi()->getResourceType($type);
                    }, $type) : array_values($context->getApi()->getResourceTypes());

                    $this->validateInclude($context, $relatedResources, $nested, $name.'.');
                }

                continue 2;
            }

            throw (new BadRequestException("Invalid include [{$path}{$name}]"))->setSourceParameter('include');
        }
    }
}
