<?php

namespace Tobyz\JsonApiServer;

use HttpAccept\ContentTypeParser;
use HttpAccept\Data\MediaType;
use InvalidArgumentException;

/**
 * The JSON:API media type with its ext and profile parameters.
 */
final class JsonApiMediaType
{
    /**
     * @param string[] $extensions
     * @param string[] $profiles
     */
    public function __construct(
        public readonly array $extensions = [],
        public readonly array $profiles = [],
    ) {}

    /**
     * Parse a Content-Type header, returning null if it is not a valid JSON:API
     * media type.
     *
     * When not strict, parameters other than ext and profile are ignored rather
     * than making the media type invalid.
     */
    public static function parse(string $contentType, bool $strict = true): ?self
    {
        try {
            $type = (new ContentTypeParser())->parse($contentType);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $type->name() === JsonApi::MEDIA_TYPE ? self::fromParameters($type, $strict) : null;
    }

    /**
     * Read the ext and profile parameters of a parsed media type, returning null
     * if it has any other parameters and is strict.
     */
    public static function fromParameters(MediaType $type, bool $strict = true): ?self
    {
        if ($strict && array_diff(array_keys($type->parameters()), ['ext', 'profile'])) {
            return null;
        }

        $uris = fn(string $name) => (
            $type->hasParamater($name)
                ? preg_split('/\s+/', $type->getParameter($name), -1, PREG_SPLIT_NO_EMPTY)
                : []
        );

        return new self($uris('ext'), $uris('profile'));
    }

    /**
     * Return a copy with additional extension and profile URIs.
     *
     * @param string[] $extensions
     * @param string[] $profiles
     */
    public function with(array $extensions = [], array $profiles = []): self
    {
        return new self(
            array_values(array_unique([...$this->extensions, ...$extensions])),
            array_values(array_unique([...$this->profiles, ...$profiles])),
        );
    }

    public function __toString(): string
    {
        $mediaType = JsonApi::MEDIA_TYPE;

        foreach (['ext' => $this->extensions, 'profile' => $this->profiles] as $param => $uris) {
            if ($uris) {
                $mediaType .= "; $param=\"" . implode(' ', $uris) . '"';
            }
        }

        return $mediaType;
    }
}
