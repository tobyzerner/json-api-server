<?php

namespace Tobyz\JsonApiServer\Translation;

interface TranslatorInterface
{
    public function translate(string $key, array $replacements = []): string;
}
