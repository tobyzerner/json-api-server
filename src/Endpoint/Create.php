<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\SavesData;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Resource\Creatable;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\set_value;

class Create implements Endpoint
{
    use HasVisibility;
    use SavesData;
    use ShowsResources;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        if (str_contains($context->path(), '/')) {
            return null;
        }

        if ($context->request->getMethod() !== 'POST') {
            throw new MethodNotAllowedException();
        }

        $collection = $context->collection;

        if (!$collection instanceof Creatable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), Creatable::class),
            );
        }

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        $data = $this->parseData($context);

        $context = $context
            ->withResource($resource = $context->resource($data['type']))
            ->withModel($model = $collection->newModel($context));

        $this->assertFieldsValid($context, $data);
        $this->fillDefaultValues($context, $data);
        $this->deserializeValues($context, $data);
        $this->assertDataValid($context, $data, true);
        $this->setValues($context, $data);

        $context = $context->withModel($model = $resource->create($model, $context));

        $this->saveFields($context, $data);

        return json_api_response($document = $this->showResource($context, $model))
            ->withStatus(201)
            ->withHeader('Location', $document['data']['links']['self']);
    }

    private function fillDefaultValues(Context $context, array &$data): void
    {
        foreach ($context->fields($context->resource) as $field) {
            if (!has_value($data, $field) && ($default = $field->default)) {
                set_value($data, $field, $default($context->withField($field)));
            }
        }
    }
}
