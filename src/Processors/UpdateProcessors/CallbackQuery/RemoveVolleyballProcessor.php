<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class RemoveVolleyballProcessor extends AbstractGameCallbackProcessor
{
    protected function handle(TelegramUpdate $update, GameInterface $game): void
    {
        $callbackQuery = $update->callbackQuery;
        $gameId = $game->getGameId();

        $result = new GameManager()->removeVolleyball($gameId, $callbackQuery->from->id);
        $this->logUserAction($callbackQuery->from, 'remove_volleyball', "gameId=$gameId");

        $callbackAnswer = match ($result) {
            EquipmentResult::Removed => CallbackAnswer::VOLLEYBALL_REMOVED,
            EquipmentResult::NotJoined => CallbackAnswer::JOIN_FIRST,
            EquipmentResult::NoneLeft => CallbackAnswer::NO_VOLLEYBALLS,
            EquipmentResult::Error => CallbackAnswer::SOMETHING_WENT_WRONG,
        };

        if (EquipmentResult::Removed === $result) {
            $this->refreshInlineMessage($callbackQuery->inlineMessageId);
        }

        $this->answerCallbackQuery($callbackQuery, $callbackAnswer);
    }
}
