<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class RemoveVolleyballProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $inlineMessageId = $callbackQuery->inlineMessageId;

        $gameManager = new GameManager();
        $gameId = $gameManager->resolveGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            $this->telegramSender->answerCallbackQuery($callbackQuery->id, CallbackAnswer::GAME_NOT_FOUND);

            return;
        }

        $result = $gameManager->removeVolleyball($gameId, $callbackQuery->from->id);

        $callbackAnswer = match ($result) {
            EquipmentResult::Removed => CallbackAnswer::VOLLEYBALL_REMOVED,
            EquipmentResult::NotJoined => CallbackAnswer::JOIN_FIRST,
            EquipmentResult::NoneLeft => CallbackAnswer::NO_VOLLEYBALLS,
            EquipmentResult::Error => CallbackAnswer::SOMETHING_WENT_WRONG,
        };

        if (EquipmentResult::Removed === $result) {
            $this->refreshInlineMessage($inlineMessageId);
        }

        $this->telegramSender->answerCallbackQuery($callbackQuery->id, $callbackAnswer);
    }
}
