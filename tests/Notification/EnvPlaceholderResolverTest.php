<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Notification\EnvPlaceholderResolver;
use PHPUnit\Framework\TestCase;

final class EnvPlaceholderResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['BREACH_TEST_VAR']);
    }

    public function testReturnsLiteralValueUnchanged(): void
    {
        self::assertSame('breach-alice', (new EnvPlaceholderResolver())->resolve('breach-alice'));
    }

    public function testReturnsNullForNullValue(): void
    {
        self::assertNull((new EnvPlaceholderResolver())->resolve(null));
    }

    public function testResolvesDefinedVariable(): void
    {
        $_ENV['BREACH_TEST_VAR'] = 'ntfy://ntfy.sh';

        self::assertSame('ntfy://ntfy.sh', (new EnvPlaceholderResolver())->resolve('%env(BREACH_TEST_VAR)%'));
    }

    public function testReturnsNullForMissingVariable(): void
    {
        self::assertNull((new EnvPlaceholderResolver())->resolve('%env(BREACH_TEST_VAR_UNSET)%'));
    }

    public function testReturnsNullForEmptyVariable(): void
    {
        $_ENV['BREACH_TEST_VAR'] = '';

        self::assertNull((new EnvPlaceholderResolver())->resolve('%env(BREACH_TEST_VAR)%'));
    }

    public function testDoesNotTreatPartialPlaceholderAsEnv(): void
    {
        self::assertSame('foo %env(BAR)% baz', (new EnvPlaceholderResolver())->resolve('foo %env(BAR)% baz'));
    }
}
