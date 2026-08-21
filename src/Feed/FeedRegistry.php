<?php

declare(strict_types=1);

namespace App\Feed;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads and validates the list of RSS/Atom feeds from feeds.yaml.
 */
final class FeedRegistry
{
    /** @var FeedSource[]|null */
    private ?array $feeds = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/feeds.yaml')]
        private readonly string $filePath,
    ) {
    }

    /**
     * @return FeedSource[]
     */
    public function all(): array
    {
        return $this->feeds ??= $this->load();
    }

    public function findByName(string $name): ?FeedSource
    {
        foreach ($this->all() as $feed) {
            if ($feed->name === $name) {
                return $feed;
            }
        }

        return null;
    }

    /**
     * @return FeedSource[]
     */
    private function load(): array
    {
        if (!is_file($this->filePath)) {
            throw new \RuntimeException(\sprintf('Feed file not found: "%s".', $this->filePath));
        }

        $data = Yaml::parseFile($this->filePath);

        if (!\is_array($data) || !\array_key_exists('feeds', $data)) {
            throw new \RuntimeException(\sprintf('The file "%s" must contain a "feeds" key.', $this->filePath));
        }

        $rawFeeds = $data['feeds'];
        if (!\is_array($rawFeeds) || [] === $rawFeeds) {
            throw new \RuntimeException(\sprintf('The "feeds" key of file "%s" must be a non-empty list.', $this->filePath));
        }

        $feeds = [];
        $seenNames = [];

        foreach ($rawFeeds as $index => $entry) {
            if (!\is_array($entry)) {
                throw new \RuntimeException(\sprintf('Entry "feeds[%d]" of file "%s" must be a mapping.', $index, $this->filePath));
            }

            $name = $entry['name'] ?? null;
            if (!\is_string($name) || '' === trim($name)) {
                throw new \RuntimeException(\sprintf('Entry "feeds[%d]" of file "%s" must have a non-empty "name".', $index, $this->filePath));
            }
            $name = trim($name);

            if (isset($seenNames[$name])) {
                throw new \RuntimeException(\sprintf('Feed name "%s" is duplicated in "%s".', $name, $this->filePath));
            }
            $seenNames[$name] = true;

            $url = $entry['url'] ?? null;
            if (!\is_string($url) || false === filter_var($url, \FILTER_VALIDATE_URL)) {
                throw new \RuntimeException(\sprintf('The URL of feed "%s" is invalid.', $name));
            }

            $feeds[] = new FeedSource($name, $url);
        }

        return $feeds;
    }
}
