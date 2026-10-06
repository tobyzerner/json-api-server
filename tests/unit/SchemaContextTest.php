<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\TestCase;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\Schema\Meta;
use Tobyz\JsonApiServer\SchemaContext;
use Tobyz\Tests\JsonApiServer\MockResource;

class SchemaContextTest extends TestCase
{
    public function test_definitions_are_only_built_once_per_resource()
    {
        $resource = new class('users') extends MockResource {
            public array $calls = ['fields' => 0, 'meta' => 0, 'links' => 0];

            public function fields(): array
            {
                $this->calls['fields']++;

                return [Attribute::make('name')];
            }

            public function meta(): array
            {
                $this->calls['meta']++;

                return [Meta::make('count')];
            }

            public function links(): array
            {
                $this->calls['links']++;

                return [Link::make('self')];
            }
        };

        $context = new SchemaContext(new JsonApi());

        foreach (['fields', 'meta', 'links'] as $method) {
            $first = $context->$method($resource);

            $this->assertSame($first, $context->$method($resource));
        }

        $this->assertSame(['fields' => 1, 'meta' => 1, 'links' => 1], $resource->calls);
    }
}
