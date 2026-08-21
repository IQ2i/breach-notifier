<?php

declare(strict_types=1);

namespace App\Notification\Transport;

use App\Notification\Channel\ChannelType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Transport;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * Resolves notifications.yaml DSNs into Notifier transports, via the bridge factory
 * aggregates registered by Symfony as soon as framework.notifier is enabled:
 * texter.transport_factory for Free Mobile (SMS), chatter.transport_factory for the
 * chat-based channels (Mattermost, Pushover).
 */
final class NotifierTransportResolver implements TransportResolverInterface
{
    private const array CHAT_CHANNEL_TYPES = [ChannelType::Mattermost, ChannelType::Pushover];

    public function __construct(
        #[Autowire(service: 'texter.transport_factory')]
        private readonly Transport $texterTransportFactory,
        #[Autowire(service: 'chatter.transport_factory')]
        private readonly Transport $chatterTransportFactory,
    ) {
    }

    public function resolve(string $dsn, ChannelType $type): TransportInterface
    {
        $factory = \in_array($type, self::CHAT_CHANNEL_TYPES, true) ? $this->chatterTransportFactory : $this->texterTransportFactory;

        return $factory->fromString($dsn);
    }
}
