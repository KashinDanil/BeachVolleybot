<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\GameAction;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\Messages\GameMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramCallbackQuery;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract class AbstractGameCallbackProcessor extends AbstractCallbackProcessor
{
    final public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $gameMessage = $callbackQuery->toGameMessage();
        $game = $this->resolveGame($gameMessage);

        if (null === $game) {
            $this->respondGameNotFound($callbackQuery, $gameMessage);

            return;
        }

        if ($this->isKickoffPast($game)) {
            $this->respondGameFinished($callbackQuery, $game);

            return;
        }

        $this->handle($update, $game);
    }

    abstract protected function handle(TelegramUpdate $update, GameInterface $game): void;

    private function resolveGame(GameMessage $gameMessage): ?GameInterface
    {
        $gameId = new GameManager()->resolveGameIdByGameMessage($gameMessage);

        if (null === $gameId) {
            return null;
        }

        return GameFactory::tryFromGameId($gameId);
    }

    private function isKickoffPast(GameInterface $game): bool
    {
        return GameDateTimeResolver::isKickoffDayPast($game->getKickoffAt());
    }

    private function respondGameNotFound(TelegramCallbackQuery $callbackQuery, GameMessage $gameMessage): void
    {
        $this->telegramSender->removeGameMessageKeyboard($gameMessage);
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_NOT_FOUND);
    }

    private function respondGameFinished(TelegramCallbackQuery $callbackQuery, GameInterface $game): void
    {
        $this->removeAllKeyboards($game);
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_ALREADY_FINISHED);
    }

    private function removeAllKeyboards(GameInterface $game): void
    {
        foreach ($game->getMessages() as $gameMessage) {
            $this->telegramSender->removeGameMessageKeyboard($gameMessage);
        }
    }
}
