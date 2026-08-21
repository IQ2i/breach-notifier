<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * Result of sending on a channel: number of recipients reached/failed and error details.
 */
final class ChannelResult
{
    private int $sent = 0;
    private int $failed = 0;

    /** @var string[] */
    private array $errors = [];

    public function recordSuccess(): void
    {
        ++$this->sent;
    }

    public function recordFailure(string $error): void
    {
        ++$this->failed;
        $this->errors[] = $error;
    }

    public function sent(): int
    {
        return $this->sent;
    }

    public function failed(): int
    {
        return $this->failed;
    }

    /** @return string[] */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isSuccess(): bool
    {
        return $this->sent > 0;
    }

    /**
     * @return array{sent: int, failed: int, errors: string[]}
     */
    public function toArray(): array
    {
        return [
            'sent' => $this->sent,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }
}
