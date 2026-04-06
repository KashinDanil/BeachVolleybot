<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AddVolleyballProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $inlineMessageId = $callbackQuery->inlineMessageId;

        $gameManager = new GameManager();
        $gameId = $gameManager->resolveGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::GAME_NOT_FOUND);

            return;
        }

        $result = $gameManager->addVolleyball($gameId, $callbackQuery->from->id);

        $callbackAnswer = match ($result) {
            EquipmentResult::Added => CallbackAnswer::VOLLEYBALL_ADDED,
            EquipmentResult::NotJoined => CallbackAnswer::JOIN_FIRST,
            EquipmentResult::Error => CallbackAnswer::SOMETHING_WENT_WRONG,
        };

        if (EquipmentResult::Added === $result) {
            $this->refreshInlineMessage($inlineMessageId);
        }

        $this->bot->answerCallbackQuery($callbackQuery->id, $callbackAnswer);
    }
}
