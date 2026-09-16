<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Queue;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\AddOns\GameAddOnRegistry;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Game\GameManager;
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
        private array $addOns = GAME_ADD_ONS,
    ) {
    }

    public function enqueue(WeatherQueuePayload $payload): void
    {
        if (!$this->weatherAddOnIsEnabled()) {
            return;
        }

        $queue = new ($this->queueClass)(self::QUEUE_PREFIX . $payload->id(), $this->baseDir);

        $queue->enqueue(new QueueMessage($payload->jsonSerialize()));
    }

    /** Keyed at enqueue time, so a caller must save the game's kickoff and venue first. */
    public function enqueueForGameId(int $gameId): void
    {
        // Before the lookup: a disabled add-on must not cost a query on the request path.
        if (!$this->weatherAddOnIsEnabled()) {
            return;
        }

        $game = new GameManager()->findGameRecordById($gameId);

        if (null === $game) {
            Logger::logVerbose('Weather enqueue skipped: game gone (id=' . $gameId . ')');

            return;
        }

        $this->enqueue(WeatherQueuePayload::forGameRecord($game));
    }

    private function weatherAddOnIsEnabled(): bool
    {
        return GameAddOnRegistry::isEnabled(WeatherAddOn::class, $this->addOns);
    }
}
