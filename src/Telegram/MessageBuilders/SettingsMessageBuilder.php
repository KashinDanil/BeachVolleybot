<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class SettingsMessageBuilder extends AbstractAdminMessageBuilder
{
    private const string HEADER_MESSAGE = 'Settings';

    public function buildMainMenu(): TelegramMessage
    {
        return $this->buildMessage(
            $this->formatHeader(self::HEADER_MESSAGE),
            [
                [
                    $this->buildActionButton(
                        AbstractLogMessageBuilder::HEADER_MESSAGE,
                        AdminCallbackData::create(AdminCallbackAction::Logs),
                    ),
                ],
                [
                    $this->buildActionButton(
                        GamesListMessageBuilder::HEADER_MESSAGE,
                        AdminCallbackData::create(AdminCallbackAction::GamesList)->withPage(1),
                    ),
                ],
            ]
        );
    }
}