<?php

declare(strict_types=1);

namespace App\Notification;

use App\Notification\Channel\ChannelType;
use App\Notification\Channel\NotificationChannel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads and validates notification channels from notifications.yaml.
 *
 * Unlike FeedRegistry/WatchlistProvider, a missing file is not an error: the application
 * works normally without any notification configured. Likewise, a channel whose DSN or
 * recipient depends on a missing or empty environment variable is silently disabled (see
 * skippedChannelIds()) rather than rejected.
 */
final class ChannelRegistry
{
    /** @var NotificationChannel[]|null */
    private ?array $channels = null;

    /** @var string[] */
    private array $skippedIds = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/notifications.yaml')]
        private readonly string $filePath,
        private readonly EnvPlaceholderResolver $envResolver = new EnvPlaceholderResolver(),
    ) {
    }

    /**
     * @return NotificationChannel[]
     */
    public function all(): array
    {
        return $this->channels ??= $this->load();
    }

    public function findById(string $id): ?NotificationChannel
    {
        foreach ($this->all() as $channel) {
            if ($channel->id === $id) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Ids of channels declared in the file but disabled for lack of an available DSN or
     * recipient — to be reported in verbose mode.
     *
     * @return string[]
     */
    public function skippedChannelIds(): array
    {
        $this->all();

        return $this->skippedIds;
    }

    /**
     * @return NotificationChannel[]
     */
    private function load(): array
    {
        $this->skippedIds = [];

        if (!is_file($this->filePath)) {
            return [];
        }

        $data = Yaml::parseFile($this->filePath);

        if (null === $data) {
            return [];
        }

        if (!\is_array($data) || !\array_key_exists('channels', $data)) {
            throw new \RuntimeException(\sprintf('The file "%s" must contain a "channels" key.', $this->filePath));
        }

        $rawChannels = $data['channels'];
        if (!\is_array($rawChannels)) {
            throw new \RuntimeException(\sprintf('The "channels" key of file "%s" must be a mapping.', $this->filePath));
        }

        $channels = [];
        $seenIds = [];

        foreach ($rawChannels as $id => $entry) {
            $id = (string) $id;
            if ('' === trim($id)) {
                throw new \RuntimeException(\sprintf('Each entry of "channels" in file "%s" must have a non-empty id.', $this->filePath));
            }

            if (!\is_array($entry)) {
                throw new \RuntimeException(\sprintf('Entry "channels.%s" of file "%s" must be a mapping.', $id, $this->filePath));
            }

            if (isset($seenIds[$id])) {
                throw new \RuntimeException(\sprintf('Channel id "%s" is duplicated in "%s".', $id, $this->filePath));
            }
            $seenIds[$id] = true;

            $typeValue = $entry['type'] ?? null;
            $type = \is_string($typeValue) ? ChannelType::tryFrom($typeValue) : null;
            if (null === $type) {
                throw new \RuntimeException(\sprintf('Channel "%s" of file "%s" has an invalid "type": "%s" (possible values: %s).', $id, $this->filePath, \is_string($typeValue) ? $typeValue : json_encode($typeValue), implode(', ', array_column(ChannelType::cases(), 'value'))));
            }

            $rawRecipient = $entry['recipient'] ?? null;
            if (!\is_string($rawRecipient) || '' === trim($rawRecipient)) {
                throw new \RuntimeException(\sprintf('Channel "%s" of file "%s" must have a non-empty "recipient".', $id, $this->filePath));
            }

            $recipient = $this->envResolver->resolve(trim($rawRecipient));

            $rawDsn = $entry['dsn'] ?? null;
            if (null !== $rawDsn && !\is_string($rawDsn)) {
                throw new \RuntimeException(\sprintf('The "dsn" of channel "%s" of file "%s" must be a string.', $id, $this->filePath));
            }
            $dsn = $this->envResolver->resolve($rawDsn);

            $rawFrom = $entry['from'] ?? null;
            if (null !== $rawFrom && !\is_string($rawFrom)) {
                throw new \RuntimeException(\sprintf('The "from" of channel "%s" of file "%s" must be a string.', $id, $this->filePath));
            }
            $from = $this->envResolver->resolve($rawFrom);

            $fromRequired = ChannelType::Email === $type;
            $usable = null !== $recipient && (!$fromRequired || null !== $from);

            if (!$usable) {
                $this->skippedIds[] = $id;
                continue;
            }

            $channels[] = new NotificationChannel($id, $type, $dsn, $from, $recipient);
        }

        return $channels;
    }
}
