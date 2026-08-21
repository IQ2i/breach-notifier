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

    public static function truncate(string $text, int $maxLength = self::MAX_LENGTH): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1).'…';
    }
}
