<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Extension;

use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseInterface as Response;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\NotImplementedException;

use function Tobyz\JsonApiServer\json_api_response;

final class Atomic extends Extension
{
    use FindsResources;

    private $path;

    public function __construct(string $path = 'operations')
    {
        $this->path = $path;
    }

    public function uri(): string
    {
        return 'https://jsonapi.org/ext/atomic';
    }

    public function handle(Context $context): ?Response
    {
        if ($context->getPath() !== '/operations') {
            return null;
        }

        $request = $context->getRequest();

        if ($request->getMethod() !== 'POST') {
            throw new MethodNotAllowedException();
        }

        $body = $request->getParsedBody();
        $operations = $body['atomic:operations'] ?? null;

        if (! is_array($operations)) {
            throw new BadRequestException('atomic:operations must be an array of operation objects');
        }

        $results = [];
        $lids = [];

        foreach ($operations as $i => $operation) {
            switch ($operation['op'] ?? null) {
                case 'add':
                    $response = $this->add($context, $operation, $lids);
                    break;

                case 'update':
                    $response = $this->update($context, $operation, $lids);
                    break;

                case 'remove':
                    $response = $this->remove($context, $operation, $lids);
                    break;

                default:
                    throw (new BadRequestException('Invalid operation'))->setSourcePointer("/atomic:operations/$i");
            }

            $results[] = json_decode($response->getBody(), true);
        }

        return json_api_response(
            ['atomic:results' => $results]
        );
    }

    private function add(Context $context, array $operation, array &$lids): Response
    {
        // TODO: support href and ref
        if (isset($operation['href']) || isset($operation['ref'])) {
            throw new NotImplementedException('href and ref are not currently supported');
        }

        $type = $operation['data']['type'];
        $resourceType = $context->getApi()->getResourceType($type);

        $request = $context->getRequest()
            ->withMethod('POST')
            ->withUri(new Uri("/$type"))
            ->withQueryParams($operation['params'] ?? [])
            ->withParsedBody(array_diff_key($this->replaceLids($operation, $lids), ['op', 'href', 'ref', 'params']));

        $context = $context->withRequest($request);

        $response = (new Endpoint\Create())->handle($context, $resourceType);

        if ($lid = $operation['data']['lid'] ?? null) {
            if ($id = json_decode($response->getBody(), true)['data']['id'] ?? null) {
                $lids[$lid] = $id;
            }
        }

        return $response;
    }

    private function update(Context $context, array $operation, array $lids): Response
    {
        // TODO: support href and ref
        if (isset($operation['href']) || isset($operation['ref'])) {
            throw new NotImplementedException('href and ref are not currently supported');
        }

        $operation = $this->replaceLids($operation, $lids);
        $type = $operation['data']['type'];
        $id = $operation['data']['id'];
        $resourceType = $context->getApi()->getResourceType($type);

        $request = $context->getRequest()
            ->withMethod('PATCH')
            ->withUri(new Uri("/$type/$id"))
            ->withQueryParams($operation['params'] ?? [])
            ->withParsedBody(array_diff_key($operation, ['op', 'href', 'ref', 'params']));

        $context = $context->withRequest($request);

        $model = $this->findResource($resourceType, $id, $context);

        return (new Endpoint\Update())->handle($context, $resourceType, $model);
    }

    private function remove(Context $context, array $operation, array $lids): Response
    {
        // TODO: support href
        if (isset($operation['href'])) {
            throw new NotImplementedException('href is not currently supported');
        }

        $operation = $this->replaceLids($operation, $lids);
        $type = $operation['ref']['type'];
        $id = $operation['ref']['id'];
        $resourceType = $context->getApi()->getResourceType($type);

        $request = $context->getRequest()
            ->withMethod('DELETE')
            ->withUri(new Uri("/$type/$id"))
            ->withQueryParams($operation['params'] ?? [])
            ->withParsedBody(array_diff_key($operation, ['op', 'href', 'ref', 'params']));

        $context = $context->withRequest($request);

        $model = $this->findResource($resourceType, $id, $context);

        return (new Endpoint\Delete())->handle($context, $resourceType, $model);
    }

    private function replaceLids(array &$array, array $lids): array
    {
        foreach ($array as $k => &$v) {
            if ($k === 'lid' && isset($lids[$v])) {
                $array['id'] = $lids[$v];
                unset($array['lid']);
                continue;
            }

            if (is_array($v)) {
                $v = $this->replaceLids($v, $lids);
            }
        }

        return $array;
    }
}
