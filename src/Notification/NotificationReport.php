<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * Aggregated notification report: one ChannelResult per channel actually attempted.
 */
final class NotificationReport
{
    /** @var array<string, ChannelResult> */
    private array $results = [];

    public function addResult(string $channelId, ChannelResult $result): void
    {
        $this->results[$channelId] = $result;
    }

    public function hasAnySuccess(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isSuccess()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[] ids of channels that succeeded at least one send
     */
    public function successfulChannelIds(): array
    {
        return array_keys(array_filter($this->results, static fn (ChannelResult $r): bool => $r->isSuccess()));
    }

    /**
     * @return array<string, string[]> error messages by channel id
     */
    public function errors(): array
    {
        $errors = [];
        foreach ($this->results as $id => $result) {
            if ([] !== $result->errors()) {
                $errors[$id] = $result->errors();
            }
        }

        return $errors;
    }

    /**
     * @return array<string, array{sent: int, failed: int, errors: string[]}>
     */
    public function toArray(): array
    {
        return array_map(static fn (ChannelResult $r): array => $r->toArray(), $this->results);
    }
}
