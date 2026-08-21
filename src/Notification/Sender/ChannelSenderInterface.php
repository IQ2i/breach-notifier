<?php

declare(strict_types=1);

namespace App\Notification\Sender;

use App\Entity\BreachMatch;
use App\Notification\Channel\ChannelType;
use App\Notification\Channel\NotificationChannel;
use App\Notification\ChannelResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Sends detected matches on a given channel type. One implementation per ChannelType,
 * automatically discovered by BreachNotifier via #[AutowireIterator].
 */
#[AutoconfigureTag]
interface ChannelSenderInterface
{
    public function supports(ChannelType $type): bool;

    /**
     * Sends $matches to each recipient of $channel, independently (the failure of one
     * recipient does not prevent the next).
     *
     * @param BreachMatch[] $matches
     */
    public function send(NotificationChannel $channel, array $matches): ChannelResult;
}
