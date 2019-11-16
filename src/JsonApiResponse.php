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

use Zend\Diactoros\Response\JsonResponse;

class JsonApiResponse extends JsonResponse
{
    public function __construct(
        $data,
        int $status = 200,
        array $headers = [],
        int $encodingOptions = self::DEFAULT_JSON_FLAGS
    ) {
        $headers['content-type'] = JsonApi::CONTENT_TYPE;

        parent::__construct($data, $status, $headers, $encodingOptions);
    }
}
