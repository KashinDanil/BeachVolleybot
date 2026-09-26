<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\GameMessageAuthorizer;

final class ViaBotMessageProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $authorized = new GameMessageAuthorizer($this->telegramSender)->authorize($update->message);

        // Pin unless the message was rejected; null (undecided) still pins — a later edit settles it.
        if (false !== $authorized) {
            new PinMessageProcessor($this->telegramSender)->process($update);
        }
    }
}
