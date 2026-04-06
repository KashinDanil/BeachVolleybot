<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use TelegramBot\Api\BotApi;

readonly class TelegramMessageSender
{
    public function __construct(
        private BotApi $bot,
    ) {
    }

    public function editInlineMessage(string $inlineMessageId, TelegramMessage $message): void
    {
        $this->bot->editMessageText(
            null,
            null,
            $message->getText()->getMessageText(),
            $message->getText()->getParseMode(),
            $message->getText()->isDisableWebPagePreview(),
            $message->getKeyboard(),
            $inlineMessageId,
        );
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text): void
    {
        $this->bot->answerCallbackQuery($callbackQueryId, $text);
    }

    public function answerInlineQuery(string $inlineQueryId, array $results): void
    {
        $this->bot->answerInlineQuery($inlineQueryId, $results);
    }

    public function deleteMessage(int $chatId, int $messageId): void
    {
        $this->bot->deleteMessage($chatId, $messageId);
    }

    public function setMessageReaction(int $chatId, int $messageId, string $emoji): void
    {
        $this->bot->call('setMessageReaction', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reaction' => json_encode([['type' => 'emoji', 'emoji' => $emoji]]),
        ]);
    }
}
