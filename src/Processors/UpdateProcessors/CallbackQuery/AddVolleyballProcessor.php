<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AddVolleyballProcessor extends AbstractCallbackProcessor
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
        $result = $gameManager->addVolleyball($gameId, $from->id, $from->firstName, $from->lastName, $from->username);
        $this->logUserAction($from, 'add_volleyball', "gameId=$gameId");

        $callbackAnswer = match ($result) {
            EquipmentResult::Added => CallbackAnswer::VOLLEYBALL_ADDED,
            EquipmentResult::Error => CallbackAnswer::SOMETHING_WENT_WRONG,
        };

        if (EquipmentResult::Added === $result) {
            $this->refreshInlineMessage($inlineMessageId);
        }

        $this->answerCallbackQuery($callbackQuery, $callbackAnswer);
    }
}
