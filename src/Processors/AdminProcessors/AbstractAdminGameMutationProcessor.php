<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;

abstract class AbstractAdminGameMutationProcessor extends AbstractAdminCallbackProcessor
{
    protected function logAdminAction(TelegramUser $admin, string $action, string $details = ''): void
    {
        $name = trim($admin->firstName . ' ' . $admin->lastName);
        Logger::logAdminAction($admin->id, $name, $admin->username, $action, $details);
    }

    protected function refreshGameInlineMessage(int $gameId): void
    {
        $inlineMessageId = new GameRepository(Connection::get())->findInlineMessageIdByGameId($gameId);

        if (null === $inlineMessageId) {
            return;
        }

        $this->refreshInlineMessage($inlineMessageId);
    }
}
