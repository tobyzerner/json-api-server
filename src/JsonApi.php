<?php

namespace Tobyz\JsonApiServer;

use Closure;
use JsonApiPhp\JsonApi\ErrorDocument;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\InternalServerErrorException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\NotAcceptableException;
use Tobyz\JsonApiServer\Exception\NotImplementedException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobyz\JsonApiServer\Handler\Concerns\FindsResources;
use Tobyz\JsonApiServer\Http\MediaTypes;

final class JsonApi implements RequestHandlerInterface
{
    const CONTENT_TYPE = 'application/vnd.api+json';

    use FindsResources;

    private $resources = [];
    private $baseUrl;

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
        $this->validateRequest($request);

        $path = $this->stripBasePath(
            $request->getUri()->getPath()
        );

        $segments = explode('/', trim($path, '/'));

        switch (count($segments)) {
            case 1:
                return $this->handleCollection($request, $segments);

            case 2:
                return $this->handleResource($request, $segments);

            case 3:
                // return $this->handleRelated($request, $resource, $model, $segments[2]);
                throw new NotImplementedException;

            case 4:
                if ($segments[2] === 'relationships') {
                    // return $this->handleRelationship($request, $resource, $model, $segments[3]);
                    throw new NotImplementedException;
                }
        }

        throw new BadRequestException;
    }

    private function validateRequest(Request $request): void
    {
        $this->validateRequestContentType($request);
        $this->validateRequestAccepts($request);
    }

    private function validateRequestContentType(Request $request): void
    {
        $header = $request->getHeaderLine('Content-Type');

        if (empty($header)) {
            return;
        }

        if ((new MediaTypes($header))->containsExactly(self::CONTENT_TYPE)) {
            return;
        }

        throw new UnsupportedMediaTypeException;
    }

    private function validateRequestAccepts(Request $request): void
    {
        $header = $request->getHeaderLine('Accept');

        if (empty($header) || $header === '*/*') {
            return;
        }

        if ((new MediaTypes($header))->containsExactly(self::CONTENT_TYPE)) {
            return;
        }

        throw new NotAcceptableException;
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

    private function handleCollection(Request $request, array $segments): Response
    {
        $resource = $this->getResource($segments[0]);

        switch ($request->getMethod()) {
            case 'GET':
                return (new Handler\Index($this, $resource))->handle($request);

            case 'POST':
                return (new Handler\Create($this, $resource))->handle($request);

            default:
                throw new MethodNotAllowedException;
        }
    }

    private function handleResource(Request $request, array $segments): Response
    {
        $resource = $this->getResource($segments[0]);
        $model = $this->findResource($request, $resource, $segments[1]);

        switch ($request->getMethod()) {
            case 'PATCH':
                return (new Handler\Update($this, $resource, $model))->handle($request);

            case 'GET':
                return (new Handler\Show($this, $resource, $model))->handle($request);

            case 'DELETE':
                return (new Handler\Delete($resource, $model))->handle($request);

            default:
                throw new MethodNotAllowedException;
        }
    }

    public function error($e)
    {
        if (! $e instanceof ErrorProviderInterface) {
            $e = new InternalServerErrorException;
        }

        $errors = $e->getJsonApiErrors();
        $status = $e->getJsonApiStatus();

        $data = new ErrorDocument(
            ...$errors
        );

        return new JsonApiResponse($data, $status);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
