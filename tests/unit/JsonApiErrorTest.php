<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;

class JsonApiErrorTest extends AbstractTestCase
{
    public function test_source_path_renders_as_pointer(): void
    {
        $error = (new BadRequestException())->prependSourcePath('a/b', 'c~d', 0);

        $error->prependSourcePointer('/data');

        $source = $error->getJsonApiError()['source'];

        $this->assertSame('/data/a~1b/c~0d/0', $source['pointer']);
        $this->assertArrayNotHasKey('path', $source);
    }

    public function test_source_path_renders_as_parameter(): void
    {
        $error = (new BadRequestException())->prependSourcePath('ids', 0);

        $error->prependSourceParameter('filter');

        $source = $error->getJsonApiError()['source'];

        $this->assertSame('filter[ids][0]', $source['parameter']);
        $this->assertArrayNotHasKey('path', $source);
    }

    public function test_internal_path_is_not_emitted_until_rendered(): void
    {
        $error = (new BadRequestException())->prependSourcePath('ids', 0);

        $this->assertArrayNotHasKey('source', $error->getJsonApiError());
    }

    public function test_explicit_source_clears_internal_path(): void
    {
        $error = (new BadRequestException())
            ->prependSourcePath('ids', 0)
            ->source(['parameter' => 'filter'])
            ->prependSourcePointer('/data');

        $source = $error->getJsonApiError()['source'];

        $this->assertSame('filter', $source['parameter']);
        $this->assertSame('/data', $source['pointer']);
    }

    public function test_prepend_source_still_prepends_source_for_backwards_compatibility(): void
    {
        $error = (new BadRequestException())
            ->prependSourcePath('ids', 0)
            ->prependSource(['parameter' => 'filter']);

        $this->assertSame(
            'filter[ids][0]',
            $error->getJsonApiError()['source']['parameter'],
        );
    }
}
