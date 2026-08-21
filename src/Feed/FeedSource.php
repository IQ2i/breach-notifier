<?php

declare(strict_types=1);

namespace App\Feed;

/**
 * An RSS/Atom feed declared in feeds.yaml.
 */
final readonly class FeedSource
{
    public function __construct(
        public string $name,
        public string $url,
    ) {
    }
}
