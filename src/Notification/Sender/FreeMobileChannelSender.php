<?php

declare(strict_types=1);

namespace App\Notification\Sender;

use App\Notification\Channel\ChannelType;
use App\Notification\Channel\NotificationChannel;
use App\Notification\ChannelResult;
use App\Notification\Transport\TransportResolverInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Twig\Environment;

/**
 * Sends a summary SMS via Free Mobile. The Free Mobile API only sends to the phone number
 * tied to the account used (FreeMobileTransport::supports() compares its "phone" to that of
 * the message): the channel's recipient is therefore not a plain phone number but a full
 * Free Mobile DSN (login + API key + number).
 */
final class FreeMobileChannelSender implements ChannelSenderInterface
{
    public function __construct(
        private readonly TransportResolverInterface $transportResolver,
        private readonly Environment $twig,
    ) {
    }

    public function supports(ChannelType $type): bool
    {
        return ChannelType::FreeMobile === $type;
    }

    public function send(NotificationChannel $channel, array $matches): ChannelResult
    {
        $result = new ChannelResult();

        $content = ShortMessageTruncator::truncate($this->twig->render('notification/short.txt.twig', ['matches' => $matches]));

        $dsn = $channel->recipient;
        $phone = $this->extractPhone($dsn);
        $label = $phone ?? 'unknown recipient';

        try {
            if (null === $phone) {
                throw new \RuntimeException('Invalid Free Mobile DSN: missing "phone" parameter.');
            }

            $transport = $this->transportResolver->resolve($dsn, $channel->type);
            $transport->send(new SmsMessage($phone, $content));
            $result->recordSuccess();
        } catch (\Throwable $e) {
            $result->recordFailure(\sprintf('%s: %s', $label, $e->getMessage()));
        }

        return $result;
    }

    private function extractPhone(string $dsn): ?string
    {
        parse_str((string) parse_url($dsn, \PHP_URL_QUERY), $query);

        return \is_string($query['phone'] ?? null) && '' !== $query['phone'] ? $query['phone'] : null;
    }
}
