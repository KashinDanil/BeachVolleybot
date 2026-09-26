<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Telegram\Messages\GameMessage;
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

    public function editGameMessage(GameMessage $gameMessage, TelegramMessage $message): bool
    {
        if ($gameMessage->isInline()) {
            return $this->editInlineMessage($gameMessage->inlineMessageId, $message);
        }

        return $this->editMessage($gameMessage->chatId, $gameMessage->messageId, $message);
    }

    public function removeGameMessageKeyboard(GameMessage $gameMessage): void
    {
        if ($gameMessage->isInline()) {
            $this->removeInlineKeyboard($gameMessage->inlineMessageId);

            return;
        }

        $this->removeChatKeyboard($gameMessage->chatId, $gameMessage->messageId);
    }

    private function editInlineMessage(string $inlineMessageId, TelegramMessage $message): bool
    {
        try {
            // Inline message: chat/message id null, identified by inline_message_id
            /** @noinspection PhpParamsInspection */
            $this->bot->editMessageText(
                null,
                null,
                $message->getText()->getMessageText(),
                $message->getText()->getParseMode(),
                $message->getText()->isDisableWebPagePreview(),
                $message->getKeyboard(),
                $inlineMessageId,
            );

            return true;
        } catch (HttpException $exception) {
            Logger::logApp('editInlineMessage failed: ' . $exception->getMessage());

            return false;
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

    private function removeInlineKeyboard(string $inlineMessageId): void
    {
        try {
            // Inline message: chat/message id null, identified by inline_message_id
            /** @noinspection PhpParamsInspection */
            $this->bot->editMessageReplyMarkup(null, null, null, $inlineMessageId);
        } catch (HttpException) {
            // Keyboard already removed or message deleted
        }
    }

    private function removeChatKeyboard(int $chatId, int $messageId): void
    {
        try {
            $this->bot->editMessageReplyMarkup($chatId, $messageId, null);
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

    public function sendMessage(int $chatId, TelegramMessage $message, ?int $messageThreadId = null): int
    {
        try {
            $result = $this->bot->sendMessage(
                $chatId,
                $message->getText()->getMessageText(),
                $message->getText()->getParseMode(),
                $message->getText()->isDisableWebPagePreview(),
                null,
                $message->getKeyboard(),
                false,
                $messageThreadId,
            );

            return (int)$result->getMessageId();
        } catch (HttpException $exception) {
            Logger::logApp('sendMessage failed: ' . $exception->getMessage());

            return 0;
        }
    }

    public function sendReply(int $chatId, int $replyToMessageId, TelegramMessage $message): int
    {
        try {
            $result = $this->bot->sendMessage(
                $chatId,
                $message->getText()->getMessageText(),
                $message->getText()->getParseMode(),
                $message->getText()->isDisableWebPagePreview(),
                $replyToMessageId,
                $message->getKeyboard(),
            );

            return (int)$result->getMessageId();
        } catch (HttpException $exception) {
            Logger::logApp('sendReply failed: ' . $exception->getMessage());

            return 0;
        }
    }

    public function sendEphemeralMessage(
        int $chatId,
        int $receiverUserId,
        int $replyToEphemeralMessageId,
        TelegramMessage $message,
        ?int $messageThreadId = null,
    ): bool {
        $data = [
            'chat_id' => $chatId,
            'text' => $message->getText()->getMessageText(),
            'parse_mode' => $message->getText()->getParseMode(),
            'disable_web_page_preview' => $message->getText()->isDisableWebPagePreview(),
            'receiver_user_id' => $receiverUserId,
            'reply_parameters' => json_encode(['ephemeral_message_id' => $replyToEphemeralMessageId]),
            'reply_markup' => $message->getKeyboard()?->toJson(),
        ];
        if (null !== $messageThreadId) {
            $data['message_thread_id'] = $messageThreadId;
        }

        try {
            $this->bot->call('sendMessage', $data);

            return true;
        } catch (HttpException $exception) {
            Logger::logApp('sendEphemeralMessage failed: ' . $exception->getMessage());

            return false;
        }
    }

    public function editEphemeralMessage(
        int $chatId,
        int $ephemeralMessageId,
        int $receiverUserId,
        TelegramMessage $message
    ): bool {
        try {
            $this->bot->call('editEphemeralMessageText', [
                'chat_id' => $chatId,
                'ephemeral_message_id' => $ephemeralMessageId,
                'receiver_user_id' => $receiverUserId,
                'text' => $message->getText()->getMessageText(),
                'parse_mode' => $message->getText()->getParseMode(),
                'disable_web_page_preview' => $message->getText()->isDisableWebPagePreview(),
                'reply_markup' => $message->getKeyboard()?->toJson(),
            ]);

            return true;
        } catch (HttpException $exception) {
            Logger::logApp('editEphemeralMessage failed: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Only deletes outgoing ephemeral messages
     *
     * @param int $chatId
     * @param int $ephemeralMessageId
     * @param int $receiverUserId
     *
     * @return void
     */
    public function deleteEphemeralMessage(int $chatId, int $ephemeralMessageId, int $receiverUserId): void
    {
        try {
            $this->bot->call('deleteEphemeralMessage', [
                'chat_id' => $chatId,
                'ephemeral_message_id' => $ephemeralMessageId,
                'receiver_user_id' => $receiverUserId,
            ]);
        } catch (HttpException $exception) {
            Logger::logApp('deleteEphemeralMessage failed: ' . $exception->getMessage());
        }
    }

    public function editMessage(int $chatId, int $messageId, TelegramMessage $message): bool
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

            return true;
        } catch (HttpException $exception) {
            Logger::logApp('editMessage failed: ' . $exception->getMessage());

            return false;
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
