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

abstract class AbstractGameCallbackProcessor extends AbstractCallbackProcessor
{
    final public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $inlineMessageId = $callbackQuery->inlineMessageId;
        $game = $this->resolveGame($inlineMessageId);

        if (null === $game) {
            $this->respondGameNotFound($callbackQuery, $inlineMessageId);

            return;
        }

        if ($this->isKickoffPast($game)) {
            $this->respondGameFinished($callbackQuery, $game);

            return;
        }

        $this->handle($update, $game);
    }

    abstract protected function handle(TelegramUpdate $update, GameInterface $game): void;

    private function resolveGame(string $inlineMessageId): ?GameInterface
    {
        $gameId = new GameManager()->resolveGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            return null;
        }

        return GameFactory::tryFromGameId($gameId);
    }

    private function isKickoffPast(GameInterface $game): bool
    {
        return GameDateTimeResolver::isKickoffDayPast($game->getTitle(), $game->getCreatedAt());
    }

    private function respondGameNotFound(TelegramCallbackQuery $callbackQuery, string $inlineMessageId): void
    {
        $this->telegramSender->removeInlineKeyboard($inlineMessageId);
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_NOT_FOUND);
    }

    private function respondGameFinished(TelegramCallbackQuery $callbackQuery, GameInterface $game): void
    {
        $this->removeAllInlineKeyboards($game);
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_ALREADY_FINISHED);
    }

    private function removeAllInlineKeyboards(GameInterface $game): void
    {
        foreach ($game->getInlineMessageIds() as $inlineMessageId) {
            $this->telegramSender->removeInlineKeyboard($inlineMessageId);
        }
    }
}
