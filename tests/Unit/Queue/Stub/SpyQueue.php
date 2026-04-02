<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue\Stub;

use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

final class SpyQueue implements QueueInterface
{
    /** @var self[] */
    public static array $instances = [];

    public int $enqueueCount = 0;
    public ?array $lastPayload = null;

    public function __construct(
        public readonly string $queueName,
        public readonly string $baseDir,
    ) {
        self::$instances[] = $this;
    }

    public static function reset(): void
    {
        self::$instances = [];
    }

    public function enqueue(QueueMessage $message): void
    {
        $this->enqueueCount++;
        $this->lastPayload = $message->payload;
    }

    public function dequeue(): ?QueueMessage
    {
        return null;
    }

    public function isEmpty(): bool
    {
        return true;
    }

    public function size(): int
    {
        return 0;
    }

    public function compact(): void
    {
    }
}