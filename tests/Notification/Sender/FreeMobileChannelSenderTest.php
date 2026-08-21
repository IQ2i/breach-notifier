<?php

declare(strict_types=1);

namespace App\Tests\Notification\Sender;

use App\Entity\BreachItem;
use App\Entity\BreachMatch;
use App\Notification\Channel\ChannelType;
use App\Notification\Channel\NotificationChannel;
use App\Notification\Sender\FreeMobileChannelSender;
use App\Notification\Transport\TransportResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class FreeMobileChannelSenderTest extends TestCase
{
    public function testExtractsPhoneFromRecipientDsn(): void
    {
        $sent = [];
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')->willReturnCallback(function (SmsMessage $message) use (&$sent) {
            $sent[] = $message;

            return null;
        });

        $resolver = $this->createMock(TransportResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturn($transport);

        $channel = new NotificationChannel('free_mobile', ChannelType::FreeMobile, null, null, 'freemobile://alice:key1@default?phone=0611111111');
        $result = (new FreeMobileChannelSender($resolver, $this->createTwig()))->send($channel, [$this->createMatch()]);

        self::assertSame(1, $result->sent());
        self::assertSame('0611111111', $sent[0]->getPhone());
        self::assertStringContainsString('SFR', $sent[0]->getSubject());
    }

    public function testRecordsFailureWhenPhoneIsMissing(): void
    {
        $resolver = $this->createMock(TransportResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $channel = new NotificationChannel('free_mobile', ChannelType::FreeMobile, null, null, 'freemobile://alice:key1@default');
        $result = (new FreeMobileChannelSender($resolver, $this->createTwig()))->send($channel, [$this->createMatch()]);

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
