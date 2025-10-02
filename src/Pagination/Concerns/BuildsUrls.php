<?php

namespace Tobyz\JsonApiServer\Pagination\Concerns;

use Tobyz\JsonApiServer\Context;

trait BuildsUrls
{
    private function buildUrl(array $params, Context $context): string
    {
        $queryParams = array_replace_recursive($context->request->getQueryParams(), $params);

        if (isset($queryParams['filter'])) {
            foreach ($queryParams['filter'] as &$v) {
                $v = $v === null ? '' : $v;
            }
        }

        ksort($queryParams);

        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        return $context->api->basePath .
            '/' .
            $context->path() .
            ($queryString ? '?' . $queryString : '');
    }
}
