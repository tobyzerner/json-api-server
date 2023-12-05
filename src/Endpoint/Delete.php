<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Resource\Deletable;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\json_api_response;

class Delete implements Endpoint
{
    use HasMeta;
    use HasVisibility;
    use FindsResources;

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

        if ($context->request->getMethod() !== 'DELETE') {
            throw new MethodNotAllowedException();
        }

        $model = $this->findResource($context, $segments[1]);

        $context = $context->withResource(
            $resource = $context->resource($context->collection->resource($model, $context)),
        );

        if (!$resource instanceof Deletable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($resource), Deletable::class),
            );
        }

        if (!$this->isVisible($context = $context->withModel($model))) {
            throw new ForbiddenException();
        }

        $resource->delete($model, $context);

        if ($meta = $this->serializeMeta($context)) {
            return json_api_response(['meta' => $meta]);
        }

        return new Response(204);
    }
}
