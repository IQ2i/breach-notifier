<?php

declare(strict_types=1);

namespace App\Tests\Matching;

use App\Matching\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextNormalizerTest extends TestCase
{
    private TextNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new TextNormalizer();
    }

    #[DataProvider('provideNormalizationCases')]
    public function testNormalize(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalize($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideNormalizationCases(): iterable
    {
        yield 'lowercase and accents' => ['Société Générale', 'societe generale'];
        yield 'typographic apostrophe' => ['l’entreprise', 'l entreprise'];
        yield 'straight apostrophe' => ["l'entreprise", 'l entreprise'];
        yield 'multiple punctuation collapses to one space' => ["SFR : plus de 2,1 millions...", 'sfr plus de 2 1 millions'];
        yield 'newlines and tabs' => ["Ligne 1\nLigne 2\tLigne 3", 'ligne 1 ligne 2 ligne 3'];
        yield 'leading and trailing punctuation trimmed' => ['  « SFR » ', 'sfr'];
        yield 'empty string' => ['', ''];
    }
}
