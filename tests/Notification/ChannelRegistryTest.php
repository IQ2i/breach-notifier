<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Notification\Channel\ChannelType;
use App\Notification\ChannelRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChannelRegistryTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'notifications_').'.yaml';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpFile);
        unset($_ENV['BREACH_TEST_DSN']);
    }

    public function testLoadsValidChannels(): void
    {
        file_put_contents($this->tmpFile, <<<YAML
            channels:
                email:
                    type: email
                    from: 'alerte@exemple.fr'
                    recipient: 'alice@exemple.fr'
                free_mobile:
                    type: free_mobile
                    recipient: 'freemobile://login:key@default?phone=0611223344'
            YAML);

        $channels = (new ChannelRegistry($this->tmpFile))->all();

        self::assertCount(2, $channels);
        self::assertSame('email', $channels[0]->id);
        self::assertSame(ChannelType::Email, $channels[0]->type);
        self::assertSame('alice@exemple.fr', $channels[0]->recipient);
        self::assertSame('free_mobile', $channels[1]->id);
        self::assertSame('freemobile://login:key@default?phone=0611223344', $channels[1]->recipient);
    }

    public function testFindByIdReturnsNullWhenUnknown(): void
    {
        file_put_contents($this->tmpFile, "channels:\n    email:\n        type: email\n        from: 'a@b.fr'\n        recipient: 'c@d.fr'");

        $registry = new ChannelRegistry($this->tmpFile);

        self::assertNotNull($registry->findById('email'));
        self::assertNull($registry->findById('inconnu'));
    }

    public function testMissingFileYieldsNoChannels(): void
    {
        self::assertSame([], (new ChannelRegistry('/nonexistent/notifications.yaml'))->all());
    }

    public function testEmptyChannelsMappingIsValid(): void
    {
        file_put_contents($this->tmpFile, 'channels: {}');

        self::assertSame([], (new ChannelRegistry($this->tmpFile))->all());
    }

    public function testChannelIsSkippedWhenEnvPlaceholderIsUnresolvable(): void
    {
        file_put_contents($this->tmpFile, <<<YAML
            channels:
                free_mobile:
                    type: free_mobile
                    recipient: '%env(BREACH_TEST_DSN)%'
            YAML);

        $registry = new ChannelRegistry($this->tmpFile);

        self::assertSame([], $registry->all());
        self::assertSame(['free_mobile'], $registry->skippedChannelIds());
    }

    public function testChannelIsUsableWhenEnvPlaceholderResolves(): void
    {
        $_ENV['BREACH_TEST_DSN'] = 'freemobile://login:key@default?phone=0611223344';

        file_put_contents($this->tmpFile, <<<YAML
            channels:
                free_mobile:
                    type: free_mobile
                    recipient: '%env(BREACH_TEST_DSN)%'
            YAML);

        $channels = (new ChannelRegistry($this->tmpFile))->all();

        self::assertCount(1, $channels);
        self::assertSame('freemobile://login:key@default?phone=0611223344', $channels[0]->recipient);
    }

    #[DataProvider('provideInvalidYaml')]
    public function testRejectsInvalidYaml(string $yaml): void
    {
        file_put_contents($this->tmpFile, $yaml);

        $this->expectException(\RuntimeException::class);

        (new ChannelRegistry($this->tmpFile))->all();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideInvalidYaml(): iterable
    {
        yield 'missing channels key' => ['foo: bar'];
        yield 'channels not a mapping' => ["channels:\n    - foo"];
        yield 'entry not a mapping' => ["channels:\n    email: 'foo'"];
        yield 'unknown type' => ["channels:\n    x:\n        type: 'sms'\n        recipient: 'a@b.fr'"];
        yield 'missing recipient' => ["channels:\n    email:\n        type: email\n        from: 'a@b.fr'"];
        yield 'empty recipient' => ["channels:\n    email:\n        type: email\n        from: 'a@b.fr'\n        recipient: ''"];
    }
}
