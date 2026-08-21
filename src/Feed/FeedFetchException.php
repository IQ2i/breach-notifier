<?php

declare(strict_types=1);

namespace App\Feed;

/**
 * Thrown when a feed could not be downloaded or parsed.
 */
final class FeedFetchException extends \RuntimeException
{
    public static function forSource(FeedSource $source, \Throwable $previous): self
    {
        return new self(\sprintf('Unable to read feed "%s" (%s): %s', $source->name, $source->url, $previous->getMessage()), previous: $previous);
    }
}
