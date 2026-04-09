<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Telegram\InlineMessageRefresher;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Telegram\TelegramMessageSender;

abstract class AbstractActionProcessor
{
    public function __construct(
        protected readonly TelegramMessageSender $telegramSender,
    ) {
    }

    abstract public function process(TelegramUpdate $update): void;

    protected function logUserAction(TelegramUser $user, string $action, string $details = ''): void
    {
        $name = trim($user->firstName . ' ' . $user->lastName);
        Logger::logUserAction($user->id, $name, $user->username, $action, $details);
    }

    protected function refreshInlineMessage(string $inlineMessageId): void
    {
        $refresher = new InlineMessageRefresher($this->telegramSender);
        $refresher->refresh($inlineMessageId);
    }
}
