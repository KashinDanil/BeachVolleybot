<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramCallbackQuery;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserRecord;

abstract class AbstractAdminCallbackProcessor extends AbstractCallbackProcessor
{
    public function __construct(
        TelegramMessageSender $telegramSender,
        protected readonly AdminCallbackData $adminCallbackData,
        protected readonly UserRecord $sender,
    ) {
        parent::__construct($telegramSender);
    }

    protected function editAdminPanelMessage(TelegramCallbackQuery $callbackQuery, TelegramMessage $message): void
    {
        $this->telegramSender->editMessage(
            $callbackQuery->message->chat->id,
            $callbackQuery->message->messageId,
            $message,
        );
    }
}
