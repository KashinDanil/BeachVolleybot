<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Queue;

use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

final readonly class WeatherEnqueuer
{
    public const string QUEUE_DIR = BASE_QUEUE_DIR . '/weather';

    private const string QUEUE_PREFIX = 'weather_';

    /**
     * @param class-string<QueueInterface> $queueClass
     */
    public function __construct(
        private string $queueClass = QUEUE_CLASS,
        private string $baseDir = self::QUEUE_DIR,
    ) {
    }

    public function enqueue(int $gameId, bool $force = false): void
    {
        $payload = new WeatherQueuePayload($gameId, $force);
        $queue = new ($this->queueClass)(self::QUEUE_PREFIX . $gameId, $this->baseDir);

        $queue->enqueue(new QueueMessage($payload->jsonSerialize()));
    }
}