<?php

declare(strict_types=1);

namespace App\Notification\Channel;

/**
 * Supported notification channel type, as declared in notifications.yaml.
 */
enum ChannelType: string
{
    case Email = 'email';
    case FreeMobile = 'free_mobile';
    case Mattermost = 'mattermost';
}
