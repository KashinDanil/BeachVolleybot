<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class RemoveNetProcessor extends AbstractGameCallbackProcessor
{
    protected function handle(TelegramUpdate $update, GameInterface $game): void
    {
        $callbackQuery = $update->callbackQuery;
        $gameId = $game->getGameId();

        $result = new GameManager()->removeNet($gameId, $callbackQuery->from->id);
        $this->logUserAction($callbackQuery->from, 'remove_net', "gameId=$gameId");

        $callbackAnswer = match ($result) {
            EquipmentResult::Removed => CallbackAnswer::NET_REMOVED,
            EquipmentResult::NotJoined => CallbackAnswer::JOIN_FIRST,
            EquipmentResult::NoneLeft => CallbackAnswer::NO_NETS,
            EquipmentResult::Error => CallbackAnswer::SOMETHING_WENT_WRONG,
        };

        if (EquipmentResult::Removed === $result) {
            $this->refreshInlineMessage($callbackQuery->inlineMessageId);
        }

        $this->answerCallbackQuery($callbackQuery, $callbackAnswer);
    }
}
