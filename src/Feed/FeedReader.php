<?php

declare(strict_types=1);

namespace App\Feed;

use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Reader as LaminasFeedReader;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads and parses an RSS/Atom feed, regardless of its format,
 * and returns a homogeneous list of FeedItem.
 */
final class FeedReader
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return FeedItem[]
     *
     * @throws FeedFetchException
     */
    public function read(FeedSource $source): array
    {
        try {
            $response = $this->httpClient->request('GET', $source->url, [
                'headers' => [
                    'User-Agent' => 'breach-notifier/1.0 (+https://github.com/iq2i/breach-notifier)',
                    'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml',
                ],
                'timeout' => 15,
            ]);
            $xml = $response->getContent();
        } catch (\Throwable $e) {
            throw FeedFetchException::forSource($source, $e);
        }

        try {
            $feed = LaminasFeedReader::importString($xml);
        } catch (\Throwable $e) {
            throw FeedFetchException::forSource($source, $e);
        }

        $items = [];
        foreach ($feed as $entry) {
            \assert($entry instanceof EntryInterface);
            $items[] = $this->toFeedItem($source, $entry);
        }

        return $items;
    }

    private function toFeedItem(FeedSource $source, EntryInterface $entry): FeedItem
    {
        $link = (string) ($entry->getLink() ?? '');
        $guid = trim((string) ($entry->getId() ?? ''));
        if ('' === $guid) {
            $guid = $link;
        }

        $title = trim((string) $entry->getTitle());
        $content = trim((string) ($entry->getContent() ?: $entry->getDescription()));
        $publishedAt = $this->toImmutable($entry->getDateModified() ?? $entry->getDateCreated());

        if ('' === $guid) {
            $guid = hash('sha256', $title.'|'.$publishedAt?->format(\DATE_ATOM));
        }

        // Some feeds (including FrenchBreaches) put a single <category> tag containing
        // a comma-separated list of values instead of one tag per value.
        $categories = array_values(array_unique(array_filter(array_map(
            trim(...),
            array_merge(...array_map(
                static fn (string $category): array => explode(',', $category),
                $entry->getCategories()->getValues(),
            )),
        ))));

        return new FeedItem(
            feedName: $source->name,
            feedUrl: $source->url,
            guid: $guid,
            title: $title,
            link: $link,
            content: $content,
            categories: $categories,
            publishedAt: $publishedAt,
        );
    }

    private function toImmutable(mixed $date): ?\DateTimeImmutable
    {
        if ($date instanceof \DateTimeImmutable) {
            return $date;
        }

        if ($date instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($date);
        }

        return null;
    }
}
