<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

final class DeletePinNotificationProcessor extends AbstractActionReplyProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $this->deleteMessage($update->message);
    }
}
