<?php

declare(strict_types=1);

namespace App\Notification\Sender;

use App\Notification\Channel\ChannelType;
use App\Notification\Channel\NotificationChannel;
use App\Notification\ChannelResult;
use App\Notification\Transport\TransportResolverInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Twig\Environment;

/**
 * Sends a short text summary of the matches as a Pushover push notification.
 *
 * Like Mattermost, Pushover has no natural "recipient" tied to a phone number: the target
 * user/group key and the application token are both carried by the DSN. The choice here is
 * the same as for the other chat-based channel: keep a full Pushover DSN as "recipient", for
 * consistency with mattermost — one notifications.yaml channel = one self-contained DSN.
 * Declaring several Pushover channels (one per user/group) remains possible by simply
 * duplicating the DSN.
 */
final class PushoverChannelSender implements ChannelSenderInterface
{
    private const int MAX_LENGTH = 1024;

    public function __construct(
        private readonly TransportResolverInterface $transportResolver,
        private readonly Environment $twig,
    ) {
    }

    public function supports(ChannelType $type): bool
    {
        return ChannelType::Pushover === $type;
    }

    public function send(NotificationChannel $channel, array $matches): ChannelResult
    {
        $result = new ChannelResult();

        $content = ShortMessageTruncator::truncate($this->twig->render('notification/pushover.txt.twig', ['matches' => $matches]), self::MAX_LENGTH);

        $dsn = $channel->recipient;
        $label = 'pushover';

        try {
            if (!$this->hasCredentials($dsn)) {
                throw new \RuntimeException('Invalid Pushover DSN: missing user key or application token.');
            }

            $transport = $this->transportResolver->resolve($dsn, $channel->type);
            $transport->send(new ChatMessage($content));
            $result->recordSuccess();
        } catch (\Throwable $e) {
            $result->recordFailure(\sprintf('%s: %s', $label, $e->getMessage()));
        }

        return $result;
    }

    private function hasCredentials(string $dsn): bool
    {
        $user = parse_url($dsn, \PHP_URL_USER);
        $pass = parse_url($dsn, \PHP_URL_PASS);

        return \is_string($user) && '' !== $user && \is_string($pass) && '' !== $pass;
    }
}
