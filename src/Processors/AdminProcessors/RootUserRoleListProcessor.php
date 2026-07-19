<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Factories\UserRoleListMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class RootUserRoleListProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $page = $this->adminCallbackData->getPage();

        $this->editSettingsMessage($update->callbackQuery, UserRoleListMessageFactory::build($page));
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
