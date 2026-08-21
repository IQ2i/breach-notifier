<?php

declare(strict_types=1);

namespace App\Feed;

/**
 * A normalized entry from an RSS/Atom feed, independent of the source format.
 */
final readonly class FeedItem
{
    /**
     * @param string[] $categories
     */
    public function __construct(
        public string $feedName,
        public string $feedUrl,
        public string $guid,
        public string $title,
        public string $link,
        public string $content,
        public array $categories,
        public ?\DateTimeImmutable $publishedAt,
    ) {
    }

    /**
     * Content fingerprint, used to detect that an existing article was edited
     * (the feed sometimes adds "Updated on ..." blocks).
     */
    public function contentHash(): string
    {
        return hash('sha256', $this->title."\0".$this->content."\0".implode(',', $this->categories));
    }
}
