<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class LocationUpdateThrottle
{
    private const int INTERVAL_SECONDS = 5;
    private const int CAPACITY         = 100;

    /** @var array<string, int> gameKey => last update Unix timestamp */
    private array $timestamps = [];

    public function isThrottled(string $gameKey): bool
    {
        $lastUpdated = $this->timestamps[$gameKey] ?? null;

        return null !== $lastUpdated
            && time() - $lastUpdated < self::INTERVAL_SECONDS;
    }

    public function touch(string $gameKey): void
    {
        if (self::CAPACITY <= count($this->timestamps)) {
            unset($this->timestamps[array_key_first($this->timestamps)]);
        }

        $this->timestamps[$gameKey] = time();
    }
}
