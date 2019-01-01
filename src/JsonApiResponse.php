<?php

namespace Tobscure\JsonApiServer;

use Zend\Diactoros\Response\JsonResponse;

class JsonApiResponse extends JsonResponse
{
    public function __construct(
        $data,
        $status = 200,
        array $headers = [],
        $encodingOptions = self::DEFAULT_JSON_FLAGS
    ) {
        $headers['content-type'] = Api::CONTENT_TYPE;

        parent::__construct($data, $status, $headers, $encodingOptions);
    }
}
