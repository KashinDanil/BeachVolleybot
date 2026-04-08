<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\HttpException;

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
        $this->bot->answerInlineQuery($inlineQueryId, $results, 0); //Do not cache answers, as this can result in repeated inline_query_ids and inconsistencies (actually, errors) while creating game records in the database.
    }

    public function removeInlineKeyboard(string $inlineMessageId): void
    {
        try {
            $this->bot->editMessageReplyMarkup(null, null, null, $inlineMessageId);
        } catch (HttpException) {
            // Keyboard already removed or message deleted
        }
    }

    public function deleteMessage(int $chatId, int $messageId): void
    {
        try {
            $this->bot->deleteMessage($chatId, $messageId);
        } catch (HttpException) {
            // Message already deleted or not found
        }
    }

    public function setMessageReaction(int $chatId, int $messageId, string $emoji): void
    {
        try {
            $this->bot->call('setMessageReaction', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reaction' => json_encode([['type' => 'emoji', 'emoji' => $emoji]]),
            ]);
        } catch (HttpException) {
            // Message not found or reaction not supported
        }
    }
}
