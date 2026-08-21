<?php

declare(strict_types=1);

namespace App\Notification\Channel;

/**
 * A notification channel declared in notifications.yaml, with its recipient
 * already resolved (any "%env(VAR)%" placeholder already substituted).
 */
final readonly class NotificationChannel
{
    /**
     * @param string $recipient email address or full Free Mobile DSN, depending on $type
     */
    public function __construct(
        public string $id,
        public ChannelType $type,
        public ?string $dsn,
        public ?string $from,
        public string $recipient,
    ) {
    }
}
