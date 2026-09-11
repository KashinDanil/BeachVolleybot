<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class NewGameCreatedMessageBuilder extends AbstractNewGameMessageBuilder
{
    public function build(): TelegramMessage
    {
        return $this->buildMessage($this->formText->buildSuccess(), []);
    }
}
