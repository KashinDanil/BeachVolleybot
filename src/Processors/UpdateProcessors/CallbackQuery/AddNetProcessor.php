<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AddNetProcessor extends AbstractActionProcessor
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

        $result = $gameManager->addNet($gameId, $callbackQuery->from->id);

        $callbackAnswer = match ($result) {
            EquipmentResult::Added => CallbackAnswer::NET_ADDED,
            EquipmentResult::NotJoined => CallbackAnswer::JOIN_FIRST,
        };

        if (EquipmentResult::Added === $result) {
            $this->refreshInlineMessage($inlineMessageId);
        }

        $this->telegramSender->answerCallbackQuery($callbackQuery->id, $callbackAnswer);
    }
}
