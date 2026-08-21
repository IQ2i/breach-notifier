<?php

declare(strict_types=1);

namespace App\Notification\Sender;

use App\Notification\Channel\ChannelType;
use App\Notification\Channel\NotificationChannel;
use App\Notification\ChannelResult;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends a detailed summary email to the channel's recipient.
 */
final class EmailChannelSender implements ChannelSenderInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function supports(ChannelType $type): bool
    {
        return ChannelType::Email === $type;
    }

    public function send(NotificationChannel $channel, array $matches): ChannelResult
    {
        $result = new ChannelResult();

        $email = (new TemplatedEmail())
            ->from(new Address($channel->from ?? ''))
            ->to(new Address($channel->recipient))
            ->subject(\sprintf('[breach-notifier] %d new data breach(es) detected', \count($matches)))
            ->htmlTemplate('email/breaches.html.twig')
            ->textTemplate('email/breaches.txt.twig')
            ->context(['matches' => $matches]);

        try {
            $this->mailer->send($email);
            $result->recordSuccess();
        } catch (\Throwable $e) {
            $result->recordFailure(\sprintf('%s: %s', $channel->recipient, $e->getMessage()));
        }

        return $result;
    }
}
