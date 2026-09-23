<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\Models\GameInterface;
use Throwable;

readonly class GameMessageRefresher
{
    public function __construct(
        private TelegramMessageSender $sender,
    ) {
    }

    public function refresh(int $gameId): void
    {
        $this->refreshGame(GameFactory::fromGameId($gameId));
    }

    /**
     * One game failing to build must not cost the others their refresh, so each is isolated.
     *
     * @param list<GameRecord> $gameRecords
     */
    public function refreshRecords(array $gameRecords): void
    {
        foreach ($gameRecords as $gameRecord) {
            try {
                $this->refreshGame(GameFactory::fromRecord($gameRecord));
            } catch (Throwable $e) {
                Logger::logApp('Game message refresh failed for game id=' . $gameRecord->gameId . ': ' . $e->getMessage());
            }
        }
    }

    public function refreshGame(GameInterface $game): void
    {
        foreach ($game->getMessages() as $gameMessage) {
            $this->sender->editGameMessage($gameMessage, $game->buildTelegramMessage($gameMessage->inlineQueryId));
        }
    }
}
