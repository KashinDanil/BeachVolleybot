<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class LocationUpdateThrottle
{
    private const int INTERVAL_SECONDS = 5;
    private const int CAPACITY         = 100;

    /** @var array<string, int> inlineQueryId => last update Unix timestamp */
    private array $timestamps = [];

    public function isThrottled(string $inlineQueryId): bool
    {
        $lastUpdated = $this->timestamps[$inlineQueryId] ?? null;

        return null !== $lastUpdated
            && time() - $lastUpdated < self::INTERVAL_SECONDS;
    }

    public function touch(string $inlineQueryId): void
    {
        if (self::CAPACITY <= count($this->timestamps)) {
            unset($this->timestamps[array_key_first($this->timestamps)]);
        }

        $this->timestamps[$inlineQueryId] = time();
    }
}
