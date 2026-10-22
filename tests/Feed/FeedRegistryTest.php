<?php

declare(strict_types=1);

namespace App\Tests\Feed;

use App\Feed\FeedRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeedRegistryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/feed-registry-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    public function testLoadsFeedsAndFindsThemByName(): void
    {
        $registry = $this->registry("feeds:\n  - name: ' One '\n    url: 'https://example.com/one.xml'\n  - name: Two\n    url: 'https://example.com/two.xml'\n");

        self::assertCount(2, $registry->all());
        self::assertSame('One', $registry->findByName('One')?->name);
        self::assertSame('https://example.com/two.xml', $registry->findByName('Two')?->url);
        self::assertNull($registry->findByName('Missing'));
    }

    public function testFailsWhenFileIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Feed file not found');

        (new FeedRegistry($this->directory.'/absent.yaml'))->all();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'no feeds key' => ["other: []\n", 'must contain a "feeds" key'];
        yield 'empty list' => ["feeds: []\n", 'non-empty list'];
        yield 'entry is not a mapping' => ["feeds:\n  - just-a-string\n", 'must be a mapping'];
        yield 'blank name' => ["feeds:\n  - name: ' '\n    url: 'https://example.com/a.xml'\n", 'non-empty "name"'];
        yield 'duplicated name' => ["feeds:\n  - name: A\n    url: 'https://example.com/a.xml'\n  - name: A\n    url: 'https://example.com/b.xml'\n", 'duplicated'];
        yield 'invalid url' => ["feeds:\n  - name: A\n    url: 'not a url'\n", 'URL of feed "A" is invalid'];
    }

    #[DataProvider('invalidFiles')]
    public function testRejectsInvalidFiles(string $yaml, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->registry($yaml)->all();
    }

    private function registry(string $yaml): FeedRegistry
    {
        $path = $this->directory.'/feeds.yaml';
        file_put_contents($path, $yaml);

        return new FeedRegistry($path);
    }
}
