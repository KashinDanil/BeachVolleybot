<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramCallbackQuery;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\Targets\GameMessageTarget;

abstract class AbstractGameCallbackProcessor extends AbstractCallbackProcessor
{
    final public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $target = $callbackQuery->toGameMessageTarget();
        $game = $this->resolveGame($target);

        if (null === $game) {
            $this->respondGameNotFound($callbackQuery, $target);

            return;
        }

        if ($this->isKickoffPast($game)) {
            $this->respondGameFinished($callbackQuery, $game);

            return;
        }

        $this->handle($update, $game);
    }

    abstract protected function handle(TelegramUpdate $update, GameInterface $game): void;

    private function resolveGame(GameMessageTarget $target): ?GameInterface
    {
        $gameId = new GameManager()->resolveGameIdByTarget($target);

        if (null === $gameId) {
            return null;
        }

        return GameFactory::tryFromGameId($gameId);
    }

    private function isKickoffPast(GameInterface $game): bool
    {
        return GameDateTimeResolver::isKickoffDayPast($game->getTitle(), $game->getCreatedAt());
    }

    private function respondGameNotFound(TelegramCallbackQuery $callbackQuery, GameMessageTarget $target): void
    {
        $this->telegramSender->removeGameMessageKeyboard($target);
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_NOT_FOUND);
    }

    private function respondGameFinished(TelegramCallbackQuery $callbackQuery, GameInterface $game): void
    {
        $this->removeAllKeyboards($game);
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_ALREADY_FINISHED);
    }

    private function removeAllKeyboards(GameInterface $game): void
    {
        foreach ($game->getMessageTargets() as $target) {
            $this->telegramSender->removeGameMessageKeyboard($target);
        }
    }
}
