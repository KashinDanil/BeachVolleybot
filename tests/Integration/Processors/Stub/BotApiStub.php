<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Stub;

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Message;

class BotApiStub extends BotApi
{
    /** @var list<array{method: string, args: list<mixed>}> */
    public array $calls = [];

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
    }

    /** @noinspection MagicMethodsValidityInspection */
    public function __destruct()
    {
    }

    public function answerCallbackQuery($callbackQueryId, $text = null, $showAlert = false, $url = null, $cacheTime = 0): true
    {
        $this->calls[] = ['method' => 'answerCallbackQuery', 'args' => func_get_args()];

        return true;
    }

    public function answerInlineQuery($inlineQueryId, $results, $cacheTime = 300, $isPersonal = false, $nextOffset = '', $switchPmText = null, $switchPmParameter = null): true
    {
        $this->calls[] = ['method' => 'answerInlineQuery', 'args' => func_get_args()];

        return true;
    }

    public function sendMessage(
        $chatId,
        $text,
        $parseMode = null,
        $disablePreview = false,
        $replyToMessageId = null,
        $replyMarkup = null,
        $disableNotification = false,
        $messageThreadId = null,
        $protectContent = null,
        $allowSendingWithoutReply = null
    ): Message {
        $this->calls[] = ['method' => 'sendMessage', 'args' => func_get_args()];

        $message = new Message();
        $message->setMessageId(42);

        return $message;
    }

    public function sendDocument(
        $chatId,
        $document,
        $caption = null,
        $replyToMessageId = null,
        $replyMarkup = null,
        $disableNotification = false,
        $parseMode = null,
        $messageThreadId = null,
        $protectContent = null,
        $allowSendingWithoutReply = null,
        $thumbnail = null
    ): Message {
        $this->calls[] = ['method' => 'sendDocument', 'args' => func_get_args()];

        $message = new Message();
        $message->setMessageId(43);

        return $message;
    }

    public function editMessageReplyMarkup($chatId, $messageId, $replyMarkup = null, $inlineMessageId = null): true
    {
        $this->calls[] = ['method' => 'editMessageReplyMarkup', 'args' => func_get_args()];

        return true;
    }

    public function editMessageText($chatId, $messageId, $text, $parseMode = null, $disablePreview = false, $replyMarkup = null, $inlineMessageId = null): true
    {
        $this->calls[] = ['method' => 'editMessageText', 'args' => func_get_args()];

        return true;
    }

    public function deleteMessage($chatId, $messageId): true
    {
        $this->calls[] = ['method' => 'deleteMessage', 'args' => func_get_args()];

        return true;
    }

    public function call($method, ?array $data = null, $timeout = 10): true
    {
        $this->calls[] = ['method' => 'call', 'args' => func_get_args()];

        return true;
    }
}