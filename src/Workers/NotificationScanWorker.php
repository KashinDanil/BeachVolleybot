<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Notifications\ShortOfPlayersNotifier;
use DanilKashin\Worker\Worker;
use DateTimeImmutable;

final class NotificationScanWorker extends Worker
{
    private const int TICK_INTERVAL_MS = 1_800_000; // 30 mins

    private DateTimeImmutable $lastScanAt;

    public function __construct(
        ?int $maxTicks = null,
        DateTimeImmutable $startedAt = new DateTimeImmutable(),
        private readonly ShortOfPlayersNotifier $shortOfPlayersNotifier = new ShortOfPlayersNotifier(),
    ) {
        parent::__construct($maxTicks);
        $this->lastScanAt = $startedAt;
    }

    protected function getTickIntervalMs(): int
    {
        return self::TICK_INTERVAL_MS;
    }

    /** Each window starts where the last one ended, so a game is never scanned twice nor skipped. */
    protected function tick(): void
    {
        $now = new DateTimeImmutable();
        $this->shortOfPlayersNotifier->notifyCrossedBetween($this->lastScanAt, $now);
        $this->lastScanAt = $now;
    }
}
