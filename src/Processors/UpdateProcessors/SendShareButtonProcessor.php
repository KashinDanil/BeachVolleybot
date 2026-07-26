<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\ShareGameReplySender;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SendShareButtonProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;
        $gameKey = GameCallbackData::extractGameKey($message);

        if (null === $gameKey) {
            return;
        }

        $gameId = new GameManager()->resolveGameIdByGameKey($gameKey);

        if (null === $gameId) {
            return;
        }

        new ShareGameReplySender($this->telegramSender)->sendInDm($message->chat, $message->messageId, $gameId, $message->from);
        $this->logUserAction($message->from, 'share_button_sent', "gameId=$gameId");
    }
}
