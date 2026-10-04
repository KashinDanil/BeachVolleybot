<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserRecord;

final class RoleGateProcessor extends AbstractActionProcessor
{
    public function __construct(
        TelegramMessageSender $telegramSender,
        private readonly UserRecord $sender,
        private readonly Role $requiredRole,
        private readonly AbstractActionProcessor $allowedProcessor,
        private readonly ?AbstractActionProcessor $deniedProcessor = null,
    ) {
        parent::__construct($telegramSender);
    }

    public function process(TelegramUpdate $update): void
    {
        if ($this->sender->role->isAtLeast($this->requiredRole)) {
            $this->allowedProcessor->process($update);

            return;
        }

        $this->deniedProcessor?->process($update);
    }
}
