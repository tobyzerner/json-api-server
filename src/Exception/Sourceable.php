<?php

namespace Tobyz\JsonApiServer\Exception;

interface Sourceable
{
    public function prependSourcePath(int|string ...$path): static;

    public function prependSourceParameter(string $parameter): static;

    public function prependSourcePointer(string $pointer): static;

    /** @deprecated Use prependSourcePath() and prependSourceParameter() or prependSourcePointer(). */
    public function prependSource(array $source): static;
}
