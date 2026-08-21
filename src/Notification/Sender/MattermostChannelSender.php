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
 * Sends a Markdown summary of the matches to a Mattermost channel. Unlike Free Mobile
 * (where the logical "recipient" is the phone number tied to the account), Mattermost has
 * no such constraint: the target channel could have been a plain name in "recipient".
 *
 * The choice here is to keep a full Mattermost DSN (host + token + ?channel=) as
 * "recipient", for consistency with the existing free_mobile channel: one notifications.yaml
 * channel = one self-contained DSN, without depending on a "dsn" shared across entries.
 * Declaring several Mattermost channels (one per room) remains possible by simply
 * duplicating the DSN with a different ?channel=.
 */
final class MattermostChannelSender implements ChannelSenderInterface
{
    public function __construct(
        private readonly TransportResolverInterface $transportResolver,
        private readonly Environment $twig,
    ) {
    }

    public function supports(ChannelType $type): bool
    {
        return ChannelType::Mattermost === $type;
    }

    public function send(NotificationChannel $channel, array $matches): ChannelResult
    {
        $result = new ChannelResult();

        $content = trim($this->twig->render('notification/mattermost.md.twig', ['matches' => $matches]));

        $dsn = $channel->recipient;
        $mattermostChannel = $this->extractChannel($dsn);
        $label = $mattermostChannel ?? 'unknown channel';

        try {
            if (null === $mattermostChannel) {
                throw new \RuntimeException('Invalid Mattermost DSN: missing "channel" parameter.');
            }

            $transport = $this->transportResolver->resolve($dsn, $channel->type);
            $transport->send(new ChatMessage($content));
            $result->recordSuccess();
        } catch (\Throwable $e) {
            $result->recordFailure(\sprintf('%s : %s', $label, $e->getMessage()));
        }

        return $result;
    }

    private function extractChannel(string $dsn): ?string
    {
        parse_str((string) parse_url($dsn, \PHP_URL_QUERY), $query);

        return \is_string($query['channel'] ?? null) && '' !== $query['channel'] ? $query['channel'] : null;
    }
}
