<?php

declare(strict_types=1);

namespace App\Tests\Feed;

use App\Feed\FeedFetchException;
use App\Feed\FeedReader;
use App\Feed\FeedSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FeedReaderTest extends TestCase
{
    public function testReadsRssItemsFromFrenchBreachesFixture(): void
    {
        $xml = file_get_contents(__DIR__.'/../Fixtures/frenchbreaches.xml');
        $source = new FeedSource('FrenchBreaches', 'https://frenchbreaches.com/feed.xml');
        $reader = new FeedReader(new MockHttpClient(new MockResponse($xml)));

        $items = $reader->read($source);

        self::assertCount(3, $items);

        $sfr = $items[2];
        self::assertSame('SFR', $sfr->title);
        self::assertSame('https://frenchbreaches.com/alertes/sfr-mrp254s7k2z2s62yzqf', $sfr->guid);
        self::assertSame('FrenchBreaches', $sfr->feedName);
        self::assertStringContainsString('Fuite de données revendiquée visant SFR', $sfr->content);
        self::assertContains('nom', $sfr->categories);
        self::assertContains("type d'offre souscrite", $sfr->categories);
        self::assertNotNull($sfr->publishedAt);
    }

    public function testReadsAtomEntries(): void
    {
        $xml = file_get_contents(__DIR__.'/../Fixtures/atom-minimal.xml');
        $source = new FeedSource('Exemple Atom', 'https://example.com/feed.atom');
        $reader = new FeedReader(new MockHttpClient(new MockResponse($xml)));

        $items = $reader->read($source);

        self::assertCount(1, $items);
        self::assertSame('Acme Corp', $items[0]->title);
        self::assertSame('urn:uuid:acme-corp-breach-1', $items[0]->guid);
        self::assertContains('email', $items[0]->categories);
    }

    public function testThrowsOnHttpError(): void
    {
        $source = new FeedSource('Broken', 'https://example.com/broken.xml');
        $reader = new FeedReader(new MockHttpClient(new MockResponse('', ['http_code' => 500])));

        $this->expectException(FeedFetchException::class);

        $reader->read($source);
    }

    public function testThrowsOnMalformedXml(): void
    {
        $source = new FeedSource('Broken', 'https://example.com/broken.xml');
        $reader = new FeedReader(new MockHttpClient(new MockResponse('<not-a-feed>')));

        $this->expectException(FeedFetchException::class);

        $reader->read($source);
    }

    public function testDifferentItemsHaveDifferentGuids(): void
    {
        $xml = file_get_contents(__DIR__.'/../Fixtures/frenchbreaches.xml');
        $source = new FeedSource('FrenchBreaches', 'https://frenchbreaches.com/feed.xml');
        $reader = new FeedReader(new MockHttpClient(new MockResponse($xml)));

        $guids = array_map(static fn ($item) => $item->guid, $reader->read($source));

        self::assertSame($guids, array_unique($guids));
    }
}
