<?php

declare(strict_types=1);

namespace App\Notification\Channel;

/**
 * Type de canal de notification supporté, tel que déclaré dans notifications.yaml.
 */
enum ChannelType: string
{
    case Email = 'email';
    case FreeMobile = 'free_mobile';
}
