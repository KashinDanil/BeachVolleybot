<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use CURLFile;
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
        try {
            $this->bot->editMessageText(
                null,
                null,
                $message->getText()->getMessageText(),
                $message->getText()->getParseMode(),
                $message->getText()->isDisableWebPagePreview(),
                $message->getKeyboard(),
                $inlineMessageId,
            );
        } catch (HttpException $exception) {
            Logger::logApp('editInlineMessage failed: ' . $exception->getMessage());
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text): void
    {
        try {
            $this->bot->answerCallbackQuery($callbackQueryId, $text);
        } catch (HttpException $exception) {
            Logger::logApp('answerCallbackQuery failed: ' . $exception->getMessage());
        }
    }

    public function answerInlineQuery(string $inlineQueryId, array $results): void
    {
        try {
            $this->bot->answerInlineQuery(
                $inlineQueryId,
                $results,
                0
            ); //Do not cache answers, as this can result in repeated inline_query_ids and inconsistencies (actually, errors) while creating game records in the database.
        } catch (HttpException $exception) {
            Logger::logApp('answerInlineQuery failed: ' . $exception->getMessage());
        }
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

    public function sendMessage(int $chatId, TelegramMessage $message): int
    {
        try {
            $result = $this->bot->sendMessage(
                $chatId,
                $message->getText()->getMessageText(),
                $message->getText()->getParseMode(),
                $message->getText()->isDisableWebPagePreview(),
                null,
                $message->getKeyboard(),
            );

            return (int)$result->getMessageId();
        } catch (HttpException $exception) {
            Logger::logApp('sendMessage failed: ' . $exception->getMessage());

            return 0;
        }
    }

    public function editMessage(int $chatId, int $messageId, TelegramMessage $message): void
    {
        try {
            $this->bot->editMessageText(
                $chatId,
                $messageId,
                $message->getText()->getMessageText(),
                $message->getText()->getParseMode(),
                $message->getText()->isDisableWebPagePreview(),
                $message->getKeyboard(),
            );
        } catch (HttpException $exception) {
            Logger::logApp('editMessage failed: ' . $exception->getMessage());
        }
    }

    public function sendDocument(int $chatId, string $filePath): void
    {
        try {
            $this->bot->sendDocument($chatId, new CURLFile($filePath));
        } catch (HttpException $exception) {
            Logger::logApp('sendDocument failed: ' . $exception->getMessage());
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

    public function pinChatMessage(int $chatId, int $messageId): bool
    {
        try {
            $this->bot->pinChatMessage($chatId, $messageId, true);

            return true;
        } catch (HttpException $exception) {
            Logger::logApp('pinChatMessage failed: ' . $exception->getMessage());

            return false;
        }
    }

    public function unpinChatMessage(int $chatId, int $messageId): bool
    {
        try {
            $this->bot->unpinChatMessage($chatId, $messageId);

            return true;
        } catch (HttpException $exception) {
            Logger::logApp('unpinChatMessage failed: ' . $exception->getMessage());

            return false;
        }
    }

}
