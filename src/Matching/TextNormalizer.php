<?php

declare(strict_types=1);

namespace App\Matching;

use function Symfony\Component\String\u;

/**
 * Normalizes text to allow a search that is insensitive to case, accents,
 * and punctuation (including typographic apostrophes).
 */
final class TextNormalizer
{
    public function normalize(string $text): string
    {
        $ascii = u($text)->lower()->ascii()->toString();

        // Any non-alphanumeric sequence becomes a single space, so that word-boundary
        // searches work regardless of the original punctuation (apostrophes, hyphens,
        // commas, line breaks...).
        $collapsed = preg_replace('/[^a-z0-9]+/u', ' ', $ascii) ?? $ascii;

        return trim($collapsed);
    }
}
