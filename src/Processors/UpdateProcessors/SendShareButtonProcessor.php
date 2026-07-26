<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\ShareGameMessageBuilder;
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

        $shareMessage = new ShareGameMessageBuilder(Translator::fromUser($message->from))->build($gameId);
        $this->telegramSender->sendReply($message->chat->id, $message->messageId, $shareMessage);
        $this->logUserAction($message->from, 'share_button_sent', "gameId=$gameId");
    }
}
