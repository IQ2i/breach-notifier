<?php

declare(strict_types=1);

namespace App\Tests\Notification\Sender;

use App\Entity\BreachItem;
use App\Entity\BreachMatch;
use App\Notification\Channel\ChannelType;
use App\Notification\Channel\NotificationChannel;
use App\Notification\Sender\EmailChannelSender;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

final class EmailChannelSenderTest extends TestCase
{
    public function testSendsEmailToRecipient(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $sent = [];
        $mailer->expects(self::once())->method('send')->willReturnCallback(function (TemplatedEmail $email) use (&$sent): void {
            $sent[] = $email;
        });

        $channel = new NotificationChannel('email', ChannelType::Email, null, 'from@example.com', 'alice@example.com');
        $result = (new EmailChannelSender($mailer))->send($channel, [$this->createMatch()]);

        self::assertSame(1, $result->sent());
        self::assertSame(0, $result->failed());
        self::assertSame('alice@example.com', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('from@example.com', $sent[0]->getFrom()[0]->getAddress());
        self::assertStringContainsString('1', $sent[0]->getSubject());
    }

    public function testRecipientFailureIsRecorded(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willThrowException(new TransportException('boom'));

        $channel = new NotificationChannel('email', ChannelType::Email, null, 'from@example.com', 'alice@example.com');
        $result = (new EmailChannelSender($mailer))->send($channel, [$this->createMatch()]);

        self::assertSame(0, $result->sent());
        self::assertSame(1, $result->failed());
        self::assertStringContainsString('alice@example.com', $result->errors()[0]);
    }

    private function createMatch(): BreachMatch
    {
        $item = new BreachItem('https://example.com/feed.xml', 'guid-1', 'SFR', 'https://example.com/sfr', 'contenu', [], null, 'hash', new \DateTimeImmutable());

        return new BreachMatch($item, 'SFR', 'SFR', 'title', 'sfr visee', new \DateTimeImmutable());
    }
}
