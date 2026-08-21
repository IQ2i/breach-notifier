<?php

declare(strict_types=1);

namespace App\Watchlist;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads and validates the list of companies to watch from watchlist.yaml.
 */
final class WatchlistProvider
{
    private const array VALID_MATCH_IN = ['title', 'content'];
    private const int MIN_TERM_LENGTH = 3;

    /** @var Company[]|null */
    private ?array $companies = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/watchlist.yaml')]
        private readonly string $filePath,
    ) {
    }

    /**
     * @return Company[]
     */
    public function all(): array
    {
        return $this->companies ??= $this->load();
    }

    /**
     * @return Company[]
     */
    private function load(): array
    {
        if (!is_file($this->filePath)) {
            throw new \RuntimeException(\sprintf('Watchlist file not found: "%s".', $this->filePath));
        }

        $data = Yaml::parseFile($this->filePath);

        if (!\is_array($data) || !\array_key_exists('companies', $data)) {
            throw new \RuntimeException(\sprintf('The file "%s" must contain a "companies" key.', $this->filePath));
        }

        $rawCompanies = $data['companies'];
        if (!\is_array($rawCompanies)) {
            throw new \RuntimeException(\sprintf('The "companies" key of file "%s" must be a list.', $this->filePath));
        }

        $companies = [];
        $seenNames = [];

        foreach ($rawCompanies as $index => $entry) {
            if (!\is_array($entry)) {
                throw new \RuntimeException(\sprintf('Entry "companies[%d]" of file "%s" must be a mapping.', $index, $this->filePath));
            }

            $name = $entry['name'] ?? null;
            if (!\is_string($name) || '' === trim($name)) {
                throw new \RuntimeException(\sprintf('Entry "companies[%d]" of file "%s" must have a non-empty "name".', $index, $this->filePath));
            }
            $name = trim($name);

            if (mb_strlen($name) < self::MIN_TERM_LENGTH) {
                throw new \RuntimeException(\sprintf('Name "%s" is too short (minimum %d characters): too many likely false positives.', $name, self::MIN_TERM_LENGTH));
            }

            $normalizedName = mb_strtolower($name);
            if (isset($seenNames[$normalizedName])) {
                throw new \RuntimeException(\sprintf('Name "%s" is duplicated in "%s".', $name, $this->filePath));
            }
            $seenNames[$normalizedName] = true;

            $aliases = $entry['aliases'] ?? [];
            if (!\is_array($aliases)) {
                throw new \RuntimeException(\sprintf('The "aliases" entry of "%s" must be a list of strings.', $name));
            }
            $aliases = array_values(array_map(
                static function (mixed $alias) use ($name): string {
                    if (!\is_string($alias) || '' === trim($alias)) {
                        throw new \RuntimeException(\sprintf('An empty alias was found for "%s".', $name));
                    }

                    return trim($alias);
                },
                $aliases,
            ));

            foreach ($aliases as $alias) {
                if (mb_strlen($alias) < self::MIN_TERM_LENGTH) {
                    throw new \RuntimeException(\sprintf('Alias "%s" of "%s" is too short (minimum %d characters).', $alias, $name, self::MIN_TERM_LENGTH));
                }
            }

            $matchIn = $entry['match_in'] ?? self::VALID_MATCH_IN;
            if (!\is_array($matchIn) || [] === $matchIn) {
                throw new \RuntimeException(\sprintf('The "match_in" entry of "%s" must be a non-empty list.', $name));
            }
            foreach ($matchIn as $field) {
                if (!\in_array($field, self::VALID_MATCH_IN, true)) {
                    throw new \RuntimeException(\sprintf('Invalid "match_in" value for "%s": "%s" (possible values: %s).', $name, (string) $field, implode(', ', self::VALID_MATCH_IN)));
                }
            }

            $companies[] = new Company($name, $aliases, array_values($matchIn));
        }

        return $companies;
    }
}
