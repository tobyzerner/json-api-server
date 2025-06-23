<?php

namespace Tobyz\JsonApiServer\OpenApi;

abstract class Decorator implements GeneratorInterface
{
    public function __construct(protected GeneratorInterface $inner)
    {
    }
}
