<?php

namespace Tobyz\JsonApiServer\Exception;

interface Sourceable
{
    public function prependSource(array $source): static;
}
