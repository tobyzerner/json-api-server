<?php

namespace Tobyz\JsonApiServer;

use Closure;
use JsonApiPhp\JsonApi;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\NotImplementedException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Handler\Concerns\FindsResources;

class Api implements RequestHandlerInterface
{
    use FindsResources;

    protected $resources = [];
    protected $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function resource(string $type, $adapter, Closure $buildSchema = null): void
    {
        $this->resources[$type] = new ResourceType($type, $adapter, $buildSchema);
    }

    public function getResources(): array
    {
        return $this->resources;
    }

    public function getResource(string $type): ResourceType
    {
        if (! isset($this->resources[$type])) {
            throw new ResourceNotFoundException($type);
        }

        return $this->resources[$type];
    }

    public function handle(Request $request): Response
    {
        $path = $this->stripBasePath(
            $request->getUri()->getPath()
        );

        $segments = explode('/', trim($path, '/'));
        $count = count($segments);

        $resource = $this->getResource($segments[0]);

        if ($count === 1) {
            switch ($request->getMethod()) {
                case 'GET':
                    return $this->handleWithHandler($request, new Handler\Index($this, $resource));

                case 'POST':
                    return $this->handleWithHandler($request, new Handler\Create($this, $resource));

                default:
                    throw new MethodNotAllowedException;
            }
        }

        $model = $this->findResource($request, $resource, $segments[1]);

        if ($count === 2) {
            switch ($request->getMethod()) {
                case 'PATCH':
                    return $this->handleWithHandler($request, new Handler\Update($this, $resource, $model));

                case 'GET':
                    return $this->handleWithHandler($request, new Handler\Show($this, $resource, $model));

                case 'DELETE':
                    return $this->handleWithHandler($request, new Handler\Delete($resource, $model));

                default:
                    throw new MethodNotAllowedException;
            }
        }

        if ($count === 3) {
            throw new NotImplementedException;

            // return $this->handleRelated($request, $resource, $model, $segments[2]);
        }

        if ($count === 4 && $segments[2] === 'relationships') {
            throw new NotImplementedException;

            // return $this->handleRelationship($request, $resource, $model, $segments[3]);
        }

        throw new BadRequestException;
    }

    private function stripBasePath(string $path): string
    {
        $basePath = parse_url($this->baseUrl, PHP_URL_PATH);

        $len = strlen($basePath);

        if (substr($path, 0, $len) === $basePath) {
            $path = substr($path, $len + 1);
        }

        return $path;
    }

    private function handleWithHandler(Request $request, RequestHandlerInterface $handler)
    {
        $request = $request->withAttribute('jsonApiHandler', $handler);

        return $handler->handle($request);
    }

    public function error($e)
    {
        if (! $e instanceof ErrorProviderInterface) {
            $e = new Exception\InternalServerErrorException;
        }

        $errors = $e->getJsonApiErrors();
        $status = $e->getJsonApiStatus();

        $data = new JsonApi\ErrorDocument(
            ...$errors
        );

        return new JsonApiResponse($data, $status);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
