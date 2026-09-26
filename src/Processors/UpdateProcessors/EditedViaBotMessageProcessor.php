<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\GameMessageAuthorizer;

// A via-bot message is edited (weather, join, leave) even in groups the bot never joined, so this check can't be dodged.
final class EditedViaBotMessageProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        new GameMessageAuthorizer($this->telegramSender)->authorize($update->editedMessage);
    }
}
