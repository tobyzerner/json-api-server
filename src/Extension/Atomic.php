<?php

namespace Tobyz\JsonApiServer\Extension;

use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseInterface as Response;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\Sourceable;

use function Tobyz\JsonApiServer\json_api_response;

class Atomic extends Extension
{
    public const URI = 'https://jsonapi.org/ext/atomic';

    public function __construct(private readonly string $path = 'operations')
    {
    }

    public function uri(): string
    {
        return static::URI;
    }

    public function handle(Context $context): ?Response
    {
        if ($context->path() !== $this->path) {
            return null;
        }

        if ($context->method() !== 'POST') {
            throw new MethodNotAllowedException();
        }

        $body = $context->body();
        $operations = $body['atomic:operations'] ?? null;

        if (!is_array($operations)) {
            throw (new BadRequestException(
                'atomic:operations must be an array of operation objects',
            ))->setSource(['pointer' => '/atomic:operations']);
        }

        $results = [];
        $lids = [];

        foreach ($operations as $i => $operation) {
            try {
                if (isset($operation['ref']) && isset($operation['href'])) {
                    throw new BadRequestException('Only one of href and ref may be specified');
                }

                $response = match ($operation['op'] ?? null) {
                    'add' => $this->add($context, $operation, $lids),
                    'update' => $this->update($context, $operation, $lids),
                    'remove' => $this->remove($context, $operation, $lids),
                    default => throw new BadRequestException('Invalid operation'),
                };
            } catch (Sourceable $e) {
                throw $e->prependSource(['pointer' => "/atomic:operations/$i"]);
            }

            $results[] = json_decode($response->getBody(), true);
        }

        return json_api_response(['atomic:results' => $results]);
    }

    private function add(Context $context, array $operation, array &$lids): Response
    {
        if (isset($operation['ref'])) {
            throw (new BadRequestException('ref is not supported for add operations'))->setSource([
                'pointer' => '/ref',
            ]);
        }

        $request = $context->request
            ->withMethod('POST')
            ->withUri(new Uri($operation['href'] ?? "/{$operation['data']['type']}"))
            ->withQueryParams($operation['params'] ?? [])
            ->withParsedBody(
                array_diff_key($this->replaceLids($operation, $lids), [
                    'op',
                    'href',
                    'ref',
                    'params',
                ]),
            );

        $response = $context->api->handle($request);

        if ($lid = $operation['data']['lid'] ?? null) {
            if ($id = json_decode($response->getBody(), true)['data']['id'] ?? null) {
                $lids[$lid] = $id;
            }
        }

        return $response;
    }

    private function update(Context $context, array $operation, array $lids): Response
    {
        $operation = $this->replaceLids($operation, $lids);

        if (isset($operation['href'])) {
            $uri = $operation['href'];
        } else {
            $ref = $operation['ref'] ?? $operation['data'];
            $uri = "/{$ref['type']}/{$ref['id']}";
        }

        $request = $context->request
            ->withMethod('PATCH')
            ->withUri(new Uri($uri))
            ->withQueryParams($operation['params'] ?? [])
            ->withParsedBody(array_diff_key($operation, ['op', 'href', 'ref', 'params']));

        return $context->api->handle($request);
    }

    private function remove(Context $context, array $operation, array $lids): Response
    {
        $operation = $this->replaceLids($operation, $lids);

        if (isset($operation['href'])) {
            $uri = $operation['href'];
        } else {
            $ref = $operation['ref'];
            $uri = "/{$ref['type']}/{$ref['id']}";
        }

        $request = $context->request
            ->withMethod('DELETE')
            ->withUri(new Uri($uri))
            ->withQueryParams($operation['params'] ?? [])
            ->withParsedBody(array_diff_key($operation, ['op', 'href', 'ref', 'params']));

        return $context->api->handle($request);
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
