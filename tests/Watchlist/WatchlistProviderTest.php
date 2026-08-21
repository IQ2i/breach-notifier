<?php

declare(strict_types=1);

namespace App\Tests\Watchlist;

use App\Watchlist\WatchlistProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WatchlistProviderTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'watchlist_').'.yaml';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpFile);
    }

    public function testLoadsValidWatchlist(): void
    {
        file_put_contents($this->tmpFile, <<<YAML
            companies:
                - name: 'SFR'
                  aliases: ['Société Française du Radiotéléphone']
                - name: 'Orange'
                  match_in: ['title']
            YAML);

        $companies = (new WatchlistProvider($this->tmpFile))->all();

        self::assertCount(2, $companies);
        self::assertSame('SFR', $companies[0]->name);
        self::assertSame(['Société Française du Radiotéléphone'], $companies[0]->aliases);
        self::assertSame(['title'], $companies[1]->matchIn);
    }

    #[DataProvider('provideInvalidYaml')]
    public function testRejectsInvalidWatchlist(string $yaml): void
    {
        file_put_contents($this->tmpFile, $yaml);

        $this->expectException(\RuntimeException::class);

        (new WatchlistProvider($this->tmpFile))->all();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidYaml(): iterable
    {
        yield 'missing companies key' => ['foo: bar'];
        yield 'company without name' => ["companies:\n    - aliases: ['x']"];
        yield 'name too short' => ["companies:\n    - name: 'AB'"];
        yield 'duplicate name' => ["companies:\n    - name: 'SFR'\n    - name: 'sfr'"];
        yield 'invalid match_in value' => ["companies:\n    - name: 'SFR'\n      match_in: ['invalid']"];
        yield 'empty alias' => ["companies:\n    - name: 'SFR'\n      aliases: ['']"];
    }

    public function testThrowsWhenFileMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        (new WatchlistProvider('/nonexistent/watchlist.yaml'))->all();
    }
}
