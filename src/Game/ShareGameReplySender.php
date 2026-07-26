<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\ShareGameMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramChat;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class ShareGameReplySender
{
    public function __construct(
        private TelegramMessageSender $sender,
    ) {
    }

    public function sendInDm(TelegramChat $chat, int $gameMessageId, int $gameId, TelegramUser $user): void
    {
        if (!$chat->isPrivate()) {
            return;
        }

        $shareMessage = new ShareGameMessageBuilder(Translator::fromUser($user))->build($gameId);
        $this->sender->sendReply($chat->id, $gameMessageId, $shareMessage);
    }
}
