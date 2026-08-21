<?php

declare(strict_types=1);

namespace App\Notification\Transport;

use App\Notification\Channel\ChannelType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Transport;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * Résout les DSN de notifications.yaml en transports Notifier, via l'agrégat de
 * factories de bridges enregistré par Symfony dès que framework.notifier est activé :
 * texter.transport_factory pour Free Mobile.
 */
final class NotifierTransportResolver implements TransportResolverInterface
{
    public function __construct(
        #[Autowire(service: 'texter.transport_factory')]
        private readonly Transport $texterTransportFactory,
    ) {
    }

    public function resolve(string $dsn, ChannelType $type): TransportInterface
    {
        return $this->texterTransportFactory->fromString($dsn);
    }
}
