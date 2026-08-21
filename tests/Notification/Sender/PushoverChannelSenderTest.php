<?php

declare(strict_types=1);

namespace App\Tests\Notification\Sender;

use App\Entity\BreachItem;
use App\Entity\BreachMatch;
use App\Notification\Channel\ChannelType;
use App\Notification\Channel\NotificationChannel;
use App\Notification\Sender\PushoverChannelSender;
use App\Notification\Transport\TransportResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class PushoverChannelSenderTest extends TestCase
{
    public function testSendsSummaryToConfiguredTransport(): void
    {
        $sent = [];
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('send')->willReturnCallback(function (ChatMessage $message) use (&$sent) {
            $sent[] = $message;

            return null;
        });

        $resolver = $this->createMock(TransportResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturn($transport);

        $channel = new NotificationChannel('pushover', ChannelType::Pushover, null, null, 'pushover://user_key:app_token@default');
        $result = (new PushoverChannelSender($resolver, $this->createTwig()))->send($channel, [$this->createMatch()]);

        self::assertSame(1, $result->sent());
        self::assertStringContainsString('SFR', $sent[0]->getSubject());
    }

    public function testRecordsFailureWhenCredentialsAreMissing(): void
    {
        $resolver = $this->createMock(TransportResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $channel = new NotificationChannel('pushover', ChannelType::Pushover, null, null, 'pushover://default');
        $result = (new PushoverChannelSender($resolver, $this->createTwig()))->send($channel, [$this->createMatch()]);

        self::assertSame(0, $result->sent());
        self::assertSame(1, $result->failed());
    }

    private function createTwig(): Environment
    {
        return new Environment(new FilesystemLoader(\dirname(__DIR__, 3).'/templates'));
    }

    private function createMatch(): BreachMatch
    {
        $item = new BreachItem('https://example.com/feed.xml', 'guid-1', 'SFR', 'https://example.com/sfr', 'contenu', [], null, 'hash', new \DateTimeImmutable());

        return new BreachMatch($item, 'SFR', 'SFR', 'title', 'sfr visee', new \DateTimeImmutable());
    }
}
