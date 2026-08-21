<?php

declare(strict_types=1);

namespace App\Notification\Sender;

/**
 * Truncates the short summary used on the Free Mobile channel, to stay within reasonable
 * limits (billing per character tier).
 */
final class ShortMessageTruncator
{
    private const int MAX_LENGTH = 900;

    public static function truncate(string $text): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_LENGTH - 1).'…';
    }
}
