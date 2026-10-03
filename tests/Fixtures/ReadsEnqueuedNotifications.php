<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Fixtures;

use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use BeachVolleybot\User\NotificationType;

trait ReadsEnqueuedNotifications
{
    /** @return list<?array> */
    protected function enqueuedNotifications(): array
    {
        return array_map(static fn(SpyQueue $queue): ?array => $queue->lastPayload, SpyQueue::$instances);
    }

    /** @return list<int> */
    protected function notifiedUserIds(NotificationType $type): array
    {
        $payloads = array_filter(
            $this->enqueuedNotifications(),
            static fn(?array $payload): bool => $type->value === $payload['type'],
        );

        return array_values(array_column($payloads, 'user_id'));
    }
}
