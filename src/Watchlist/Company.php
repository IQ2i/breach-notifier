<?php

declare(strict_types=1);

namespace App\Watchlist;

/**
 * A company to watch for in the RSS feeds.
 */
final readonly class Company
{
    /**
     * @param string[] $aliases
     * @param string[] $matchIn Fields to search in: 'title', 'content'
     */
    public function __construct(
        public string $name,
        public array $aliases = [],
        public array $matchIn = ['title', 'content'],
    ) {
    }

    /**
     * All terms to search for this company (name + aliases).
     *
     * @return string[]
     */
    public function terms(): array
    {
        return [$this->name, ...$this->aliases];
    }

    public function searchesInTitle(): bool
    {
        return \in_array('title', $this->matchIn, true);
    }

    public function searchesInContent(): bool
    {
        return \in_array('content', $this->matchIn, true);
    }
}
