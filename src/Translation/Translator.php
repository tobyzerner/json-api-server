<?php

namespace Tobyz\JsonApiServer\Translation;

class Translator implements TranslatorInterface
{
    public function __construct(private array $messages = [])
    {
    }

    public function translate(string $key, array $replacements = []): string
    {
        $translation = $this->messages[$key] ?? $key;

        return $this->applyReplacements($translation, $replacements);
    }

    public function merge(array $messages): void
    {
        $this->messages = array_replace($this->messages, $messages);
    }

    public function replace(array $messages): void
    {
        $this->messages = $messages;
    }

    private function applyReplacements(string $translation, array $replacements): string
    {
        if (!$replacements) {
            return $translation;
        }

        $lookup = [];

        foreach ($replacements as $name => $value) {
            $lookup[':' . $name] = (string) $value;
        }

        return strtr($translation, $lookup);
    }
}
