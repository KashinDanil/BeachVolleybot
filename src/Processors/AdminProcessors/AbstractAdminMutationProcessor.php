<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;

abstract class AbstractAdminMutationProcessor extends AbstractAdminCallbackProcessor
{
    protected function logAdminAction(TelegramUser $admin, string $action, string $details = ''): void
    {
        $name = trim($admin->firstName . ' ' . $admin->lastName);
        Logger::logAdminAction($admin->id, $name, $admin->username, $action, $details);
    }
}
