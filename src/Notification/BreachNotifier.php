<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\BreachMatch;
use App\Notification\Channel\ChannelType;
use App\Notification\Sender\ChannelSenderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Orchestrates sending detected matches to the channels from notifications.yaml.
 *
 * One ChannelSenderInterface per channel type, iterated best-effort: the failure of one channel
 * (or of one recipient within a channel, handled by the sender itself) does not prevent the next.
 */
final class BreachNotifier
{
    /**
     * @param iterable<ChannelSenderInterface> $senders
     */
    public function __construct(
        private readonly ChannelRegistry $channelRegistry,
        #[AutowireIterator(ChannelSenderInterface::class)]
        private readonly iterable $senders,
    ) {
    }

    public function isConfigured(): bool
    {
        return [] !== $this->channelRegistry->all();
    }

    /**
     * @param BreachMatch[] $matches
     */
    public function notify(array $matches, ?string $onlyChannelId = null): NotificationReport
    {
        $report = new NotificationReport();

        if ([] === $matches) {
            return $report;
        }

        foreach ($this->channelRegistry->all() as $channel) {
            if (null !== $onlyChannelId && $channel->id !== $onlyChannelId) {
                continue;
            }

            $sender = $this->findSender($channel->type);
            if (null === $sender) {
                continue;
            }

            try {
                $report->addResult($channel->id, $sender->send($channel, $matches));
            } catch (\Throwable $e) {
                $result = new ChannelResult();
                $result->recordFailure($e->getMessage());
                $report->addResult($channel->id, $result);
            }
        }

        return $report;
    }

    private function findSender(ChannelType $type): ?ChannelSenderInterface
    {
        foreach ($this->senders as $sender) {
            if ($sender->supports($type)) {
                return $sender;
            }
        }

        return null;
    }
}
