<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Stub;

use TelegramBot\Api\BotApi;

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