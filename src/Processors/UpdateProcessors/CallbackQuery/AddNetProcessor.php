<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AddNetProcessor extends AbstractCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $inlineMessageId = $callbackQuery->inlineMessageId;

        $gameManager = new GameManager();
        $gameId = $gameManager->resolveGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            $this->telegramSender->removeInlineKeyboard($inlineMessageId);
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_NOT_FOUND);

            return;
        }

        $from = $callbackQuery->from;
        $result = $gameManager->addNet($gameId, $from->id, $from->firstName, $from->lastName, $from->username);

        $callbackAnswer = match ($result) {
            EquipmentResult::Added => CallbackAnswer::NET_ADDED,
            EquipmentResult::Error => CallbackAnswer::SOMETHING_WENT_WRONG,
        };

        if (EquipmentResult::Added === $result) {
            $this->refreshInlineMessage($inlineMessageId);
        }

        $this->answerCallbackQuery($callbackQuery, $callbackAnswer);
    }
}
