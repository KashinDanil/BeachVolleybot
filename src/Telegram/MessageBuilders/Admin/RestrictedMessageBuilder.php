<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Admin;

use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class RestrictedMessageBuilder extends AbstractAdminMessageBuilder
{
    public const string HEADER_MESSAGE = 'Access restricted';

    public function build(): TelegramMessage
    {
        return $this->buildMessage($this->formatHeader(self::HEADER_MESSAGE), []);
    }
}
