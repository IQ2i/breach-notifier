<?php

declare(strict_types=1);

namespace App\Tests\Matching;

use App\Matching\CompanyMatcher;
use App\Matching\TextNormalizer;
use App\Watchlist\Company;
use PHPUnit\Framework\TestCase;

final class CompanyMatcherTest extends TestCase
{
    private CompanyMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new CompanyMatcher(new TextNormalizer());
    }

    public function testMatchesCompanyNameInTitle(): void
    {
        $companies = [new Company('SFR')];

        $results = $this->matcher->match($companies, 'SFR: more than 2.1 million rows affected', 'irrelevant');

        self::assertCount(1, $results);
        self::assertSame('SFR', $results[0]->company->name);
        self::assertSame('title', $results[0]->matchedField);
    }

    public function testDoesNotMatchSubstringWithinAnotherWord(): void
    {
        $companies = [new Company('SFR')];

        $results = $this->matcher->match($companies, 'SFRAIS announces a funding round', 'irrelevant');

        self::assertSame([], $results);
    }

    public function testMatchesViaAlias(): void
    {
        $companies = [new Company('SFR', aliases: ['Société Française du Radiotéléphone'])];

        $results = $this->matcher->match(
            $companies,
            'Data breach',
            'An incident affecting Société Française du Radiotéléphone has been revealed.',
        );

        self::assertCount(1, $results);
        self::assertSame('Société Française du Radiotéléphone', $results[0]->matchedTerm);
        self::assertSame('content', $results[0]->matchedField);
    }

    public function testTitleOnlyCompanyIgnoresContentMentions(): void
    {
        $companies = [new Company('Orange', matchIn: ['title'])];

        $results = $this->matcher->match(
            $companies,
            'An operator hit by a breach',
            'The vendor notably worked for Orange.',
        );

        self::assertSame([], $results);
    }

    public function testReturnsNoResultWhenNothingMatches(): void
    {
        $companies = [new Company('SFR'), new Company('Orange')];

        $results = $this->matcher->match($companies, 'An unknown company', 'unrelated content');

        self::assertSame([], $results);
    }

    public function testMatchesMultipleCompaniesIndependently(): void
    {
        $companies = [new Company('SFR'), new Company('Orange', matchIn: ['title'])];

        $results = $this->matcher->match($companies, 'SFR and Orange hit by a joint breach', '');

        self::assertCount(2, $results);
    }

    public function testAccentAndCaseInsensitive(): void
    {
        $companies = [new Company('Société Générale')];

        $results = $this->matcher->match($companies, 'SOCIETE GENERALE visée par une fuite', '');

        self::assertCount(1, $results);
    }
}
