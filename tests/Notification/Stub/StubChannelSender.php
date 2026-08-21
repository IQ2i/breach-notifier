<?php

declare(strict_types=1);

namespace App\Tests\Notification\Stub;

use App\Notification\Channel\ChannelType;
use App\Notification\Channel\NotificationChannel;
use App\Notification\ChannelResult;
use App\Notification\Sender\ChannelSenderInterface;

/**
 * Test double for ChannelSenderInterface: simulates success, unless the channel's recipient
 * is listed in $failingRecipients, or throws directly if $throws is provided.
 */
final class StubChannelSender implements ChannelSenderInterface
{
    /** @var array<string, NotificationChannel[]> */
    public array $calls = [];

    /**
     * @param string[] $failingRecipients
     */
    public function __construct(
        private readonly ChannelType $type,
        private readonly array $failingRecipients = [],
        private readonly ?\Throwable $throws = null,
    ) {
    }

    public function supports(ChannelType $type): bool
    {
        return $this->type === $type;
    }

    public function send(NotificationChannel $channel, array $matches): ChannelResult
    {
        $this->calls[] = $channel;

        if (null !== $this->throws) {
            throw $this->throws;
        }

        $result = new ChannelResult();
        if (\in_array($channel->recipient, $this->failingRecipients, true)) {
            $result->recordFailure(\sprintf('%s: simulated failure', $channel->recipient));
        } else {
            $result->recordSuccess();
        }

        return $result;
    }
}
