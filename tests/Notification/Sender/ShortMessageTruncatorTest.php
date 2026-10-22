<?php

declare(strict_types=1);

namespace App\Tests\Notification\Sender;

use App\Notification\Sender\ShortMessageTruncator;
use PHPUnit\Framework\TestCase;

final class ShortMessageTruncatorTest extends TestCase
{
    public function testShortTextIsOnlyTrimmed(): void
    {
        self::assertSame('hello', ShortMessageTruncator::truncate("  hello \n"));
    }

    public function testLongTextIsTruncatedWithEllipsis(): void
    {
        self::assertSame('abcd…', ShortMessageTruncator::truncate('abcdefghij', 5));
    }

    public function testTruncationCountsCharactersNotBytes(): void
    {
        self::assertSame('éééé…', ShortMessageTruncator::truncate('éééééééé', 5));
    }

    public function testDefaultLimitIs900Characters(): void
    {
        self::assertSame(900, mb_strlen(ShortMessageTruncator::truncate(str_repeat('a', 1000))));
    }
}
