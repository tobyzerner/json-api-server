<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\json_api_response;

class Show implements Endpoint
{
    use HasVisibility;
    use FindsResources;
    use ShowsResources;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = explode('/', $context->path());

        if (count($segments) !== 2) {
            return null;
        }

        if ($context->request->getMethod() !== 'GET') {
            throw new MethodNotAllowedException();
        }

        $model = $this->findResource($context, $segments[1]);

        if (!$this->isVisible($context = $context->withModel($model))) {
            throw new ForbiddenException();
        }

        return json_api_response($this->showResource($context, $model));
    }
}
