<?php

declare(strict_types=1);

namespace App\Matching;

use App\Watchlist\Company;

/**
 * Searches for watched companies in the title and content of a feed item.
 */
final class CompanyMatcher
{
    private const int SNIPPET_RADIUS = 80;

    public function __construct(
        private readonly TextNormalizer $normalizer,
    ) {
    }

    /**
     * @param Company[] $companies
     *
     * @return MatchResult[] at most one match per company (title takes priority over content)
     */
    public function match(array $companies, string $title, string $content): array
    {
        $normalizedTitle = $this->normalizer->normalize($title);
        $normalizedContent = $this->normalizer->normalize($content);

        $results = [];

        foreach ($companies as $company) {
            $result = null;

            if ($company->searchesInTitle()) {
                $result = $this->findInField($company, $normalizedTitle, 'title');
            }

            if (null === $result && $company->searchesInContent()) {
                $result = $this->findInField($company, $normalizedContent, 'content');
            }

            if (null !== $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    private function findInField(Company $company, string $normalizedField, string $fieldName): ?MatchResult
    {
        if ('' === $normalizedField) {
            return null;
        }

        foreach ($company->terms() as $term) {
            $normalizedTerm = $this->normalizer->normalize($term);
            if ('' === $normalizedTerm) {
                continue;
            }

            // The normalized text now only contains [a-z0-9 ], which makes these boundaries
            // reliable even for a multi-word term (where \b would fail on a space).
            $pattern = '/(?<![a-z0-9])'.preg_quote($normalizedTerm, '/').'(?![a-z0-9])/u';

            if (1 === preg_match($pattern, $normalizedField, $matches, \PREG_OFFSET_CAPTURE)) {
                [$matchedText, $offset] = $matches[0];

                return new MatchResult(
                    company: $company,
                    matchedTerm: $term,
                    matchedField: $fieldName,
                    snippet: $this->extractSnippet($normalizedField, $offset, \strlen($matchedText)),
                );
            }
        }

        return null;
    }

    private function extractSnippet(string $normalizedField, int $offset, int $length): string
    {
        $start = max(0, $offset - self::SNIPPET_RADIUS);
        $end = min(\strlen($normalizedField), $offset + $length + self::SNIPPET_RADIUS);

        $snippet = substr($normalizedField, $start, $end - $start);

        return ($start > 0 ? '… ' : '').trim($snippet).($end < \strlen($normalizedField) ? ' …' : '');
    }
}
