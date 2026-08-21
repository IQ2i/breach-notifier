<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * Resolves "%env(VAR)%" placeholders in a value coming from notifications.yaml.
 *
 * This file is read directly by ChannelRegistry via Yaml::parseFile(), outside the container:
 * unlike service configuration files, its %env()% placeholders are not automatically resolved
 * by Symfony.
 */
final class EnvPlaceholderResolver
{
    private const string PLACEHOLDER_PATTERN = '/^%env\(([A-Z][A-Z0-9_]*)\)%$/';

    /**
     * Returns null if $value is a %env(VAR)% placeholder pointing to a missing or empty
     * environment variable. Returns $value unchanged if it is not a placeholder (literal
     * value).
     */
    public function resolve(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (1 !== preg_match(self::PLACEHOLDER_PATTERN, $value, $matches)) {
            return $value;
        }

        $resolved = $_ENV[$matches[1]] ?? $_SERVER[$matches[1]] ?? getenv($matches[1]);

        if (false === $resolved || !\is_string($resolved) || '' === $resolved) {
            return null;
        }

        return $resolved;
    }
}
