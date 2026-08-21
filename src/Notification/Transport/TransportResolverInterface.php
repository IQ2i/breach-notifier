<?php

declare(strict_types=1);

namespace App\Notification\Transport;

use App\Notification\Channel\ChannelType;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * Builds a Notifier transport from a DSN, for a given channel type.
 *
 * Application-level interface deliberately introduced between the senders and
 * Symfony\Component\Notifier\Transport (a final, non-mockable class) to keep the senders
 * testable without network access.
 */
interface TransportResolverInterface
{
    public function resolve(string $dsn, ChannelType $type): TransportInterface;
}
