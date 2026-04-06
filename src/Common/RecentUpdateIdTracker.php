<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class RecentUpdateIdTracker
{
    private const int CAPACITY = 100;

    /** @var array<int, true> tracked update IDs, insertion-ordered */
    private array $trackedUpdateIds = [];

    /**
     * Returns true if the update is new, false if it was already tracked
     */
    public function isTracked(int $updateId): bool
    {
        if (isset($this->trackedUpdateIds[$updateId])) {
            return true;
        }

        $this->track($updateId);

        return false;
    }

    private function track(int $updateId): void
    {
        if (self::CAPACITY <= count($this->trackedUpdateIds)) {
            unset($this->trackedUpdateIds[array_key_first($this->trackedUpdateIds)]);
        }

        $this->trackedUpdateIds[$updateId] = true;
    }
}