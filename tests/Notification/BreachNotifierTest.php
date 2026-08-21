<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Entity\BreachItem;
use App\Entity\BreachMatch;
use App\Notification\BreachNotifier;
use App\Notification\Channel\ChannelType;
use App\Notification\ChannelRegistry;
use App\Tests\Notification\Stub\StubChannelSender;
use PHPUnit\Framework\TestCase;

final class BreachNotifierTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'notifications_').'.yaml';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpFile);
    }

    public function testIsConfiguredReflectsChannelRegistry(): void
    {
        file_put_contents($this->tmpFile, 'channels: {}');
        $notifier = new BreachNotifier(new ChannelRegistry($this->tmpFile), []);

        self::assertFalse($notifier->isConfigured());
    }

    public function testNotifyDoesNothingWithoutMatches(): void
    {
        $sender = new StubChannelSender(ChannelType::Email);
        $this->writeChannels(<<<YAML
            channels:
                email:
                    type: email
                    from: 'from@example.com'
                    recipient: 'to@example.com'
            YAML);

        (new BreachNotifier(new ChannelRegistry($this->tmpFile), [$sender]))->notify([]);

        self::assertSame([], $sender->calls);
    }

    public function testNotifyDispatchesToEveryChannel(): void
    {
        $emailSender = new StubChannelSender(ChannelType::Email);
        $freeMobileSender = new StubChannelSender(ChannelType::FreeMobile);
        $this->writeChannels(<<<YAML
            channels:
                email:
                    type: email
                    from: 'from@example.com'
                    recipient: 'alice@example.com'
                free_mobile:
                    type: free_mobile
                    recipient: 'freemobile://login:key@default?phone=0611223344'
            YAML);

        $report = (new BreachNotifier(new ChannelRegistry($this->tmpFile), [$emailSender, $freeMobileSender]))->notify([$this->createMatch()]);

        self::assertSame(1, $report->toArray()['email']['sent']);
        self::assertSame(1, $report->toArray()['free_mobile']['sent']);
        self::assertTrue($report->hasAnySuccess());
        self::assertSame(['email', 'free_mobile'], $report->successfulChannelIds());
    }

    public function testAChannelWithoutMatchingSenderIsIgnored(): void
    {
        $this->writeChannels(<<<YAML
            channels:
                email:
                    type: email
                    from: 'from@example.com'
                    recipient: 'to@example.com'
            YAML);

        $report = (new BreachNotifier(new ChannelRegistry($this->tmpFile), []))->notify([$this->createMatch()]);

        self::assertFalse($report->hasAnySuccess());
        self::assertSame([], $report->toArray());
    }

    public function testASenderThrowingDoesNotBlockOtherChannels(): void
    {
        $failing = new StubChannelSender(ChannelType::Email, throws: new \RuntimeException('service indisponible'));
        $working = new StubChannelSender(ChannelType::FreeMobile);
        $this->writeChannels(<<<YAML
            channels:
                email:
                    type: email
                    from: 'from@example.com'
                    recipient: 'to@example.com'
                free_mobile:
                    type: free_mobile
                    recipient: 'freemobile://login:key@default?phone=0611223344'
            YAML);

        $report = (new BreachNotifier(new ChannelRegistry($this->tmpFile), [$failing, $working]))->notify([$this->createMatch()]);

        self::assertSame(0, $report->toArray()['email']['sent']);
        self::assertSame(1, $report->toArray()['email']['failed']);
        self::assertStringContainsString('service indisponible', $report->toArray()['email']['errors'][0]);
        self::assertSame(1, $report->toArray()['free_mobile']['sent']);
    }

    public function testOnlyChannelIdRestrictsNotificationToOneChannel(): void
    {
        $emailSender = new StubChannelSender(ChannelType::Email);
        $freeMobileSender = new StubChannelSender(ChannelType::FreeMobile);
        $this->writeChannels(<<<YAML
            channels:
                email:
                    type: email
                    from: 'from@example.com'
                    recipient: 'to@example.com'
                free_mobile:
                    type: free_mobile
                    recipient: 'freemobile://login:key@default?phone=0611223344'
            YAML);

        (new BreachNotifier(new ChannelRegistry($this->tmpFile), [$emailSender, $freeMobileSender]))->notify([$this->createMatch()], 'free_mobile');

        self::assertSame([], $emailSender->calls);
        self::assertCount(1, $freeMobileSender->calls);
    }

    private function writeChannels(string $yaml): void
    {
        file_put_contents($this->tmpFile, $yaml);
    }

    private function createMatch(): BreachMatch
    {
        $item = new BreachItem('https://example.com/feed.xml', 'guid-1', 'SFR', 'https://example.com/sfr', 'contenu', [], null, 'hash', new \DateTimeImmutable());

        return new BreachMatch($item, 'SFR', 'SFR', 'title', 'sfr visee', new \DateTimeImmutable());
    }
}
