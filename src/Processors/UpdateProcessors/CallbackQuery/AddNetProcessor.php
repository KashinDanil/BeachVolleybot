<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\EquipmentResult;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AddNetProcessor extends AbstractGameCallbackProcessor
{
    protected function handle(TelegramUpdate $update, GameInterface $game): void
    {
        $callbackQuery = $update->callbackQuery;
        $from = $callbackQuery->from;
        $gameId = $game->getGameId();

        $result = new GameManager()->addNet($gameId, $from->id, $from->firstName, $from->lastName, $from->username);
        $this->logUserAction($from, 'add_net', "gameId=$gameId");

        $callbackAnswer = match ($result) {
            EquipmentResult::Added => CallbackAnswer::NET_ADDED,
            EquipmentResult::Error => CallbackAnswer::SOMETHING_WENT_WRONG,
        };

        if (EquipmentResult::Added === $result) {
            $this->refreshInlineMessage($callbackQuery->inlineMessageId);
        }

        $this->answerCallbackQuery($callbackQuery, $callbackAnswer);
    }
}
