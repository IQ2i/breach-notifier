<?php

declare(strict_types=1);

namespace App\Matching;

use App\Watchlist\Company;

/**
 * The result of a match between a watched company and a feed item.
 */
final readonly class MatchResult
{
    public function __construct(
        public Company $company,
        public string $matchedTerm,
        public string $matchedField,
        public string $snippet,
    ) {
    }
}
