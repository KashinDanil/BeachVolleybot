<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\Role;

final class SettingsMessageBuilder extends AbstractAdminMessageBuilder
{
    private const string HEADER_MESSAGE = 'Settings';

    public function buildMainMenu(Role $role): TelegramMessage
    {
        $keyboard = [];

        $logsAction = AdminCallbackAction::Logs;
        if ($role->isAtLeast($logsAction->requiredRole())) {
            $keyboard[] = [
                $this->buildActionButton(
                    AbstractLogMessageBuilder::HEADER_MESSAGE,
                    AdminCallbackData::create($logsAction),
                ),
            ];
        }

        $keyboard[] = [
            $this->buildActionButton(
                GamesListMessageBuilder::HEADER_MESSAGE,
                AdminCallbackData::create(AdminCallbackAction::GamesList)->withPage(1),
            ),
        ];

        return $this->buildMessage($this->formatHeader(self::HEADER_MESSAGE), $keyboard);
    }
}
