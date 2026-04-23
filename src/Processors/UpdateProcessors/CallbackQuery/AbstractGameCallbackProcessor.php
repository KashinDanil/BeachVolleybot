<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

abstract class AbstractGameCallbackProcessor extends AbstractCallbackProcessor
{
    final public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $inlineMessageId = $callbackQuery->inlineMessageId;

        $gameId = new GameManager()->resolveGameIdByInlineMessageId($inlineMessageId);
        if (null === $gameId || null === ($game = GameFactory::tryFromGameId($gameId))) {
            $this->telegramSender->removeInlineKeyboard($inlineMessageId);
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_NOT_FOUND);

            return;
        }

        if (GameDateTimeResolver::isKickoffDayPast($game->getTitle(), $game->getCreatedAt())) {
            $this->telegramSender->removeInlineKeyboard($inlineMessageId);
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_ALREADY_FINISHED);

            return;
        }

        $this->handle($update, $game);
    }

    abstract protected function handle(TelegramUpdate $update, GameInterface $game): void;
}
