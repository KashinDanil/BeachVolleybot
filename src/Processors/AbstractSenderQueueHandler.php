<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;
use LogicException;

/**
 * A queued handler whose update comes from a user: the worker ensures the sender's record once and hands it over.
 */
abstract readonly class AbstractSenderQueueHandler extends AbstractQueuedProcessorHandler
{
    final public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return $this->createSenderProcessor($telegramSender, $update, $this->ensureSender($update));
    }

    abstract protected function createSenderProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
        UserRecord $sender,
    ): AbstractActionProcessor;

    private function ensureSender(TelegramUpdate $update): UserRecord
    {
        $from = $update->getFrom() ?? throw new LogicException('A queued message or callback query must always have a sender');

        if ($update->getChat()?->isPrivate()) {
            return new UserManager()->ensureUserRecordWithNotificationSettings($from);
        }

        return new UserManager()->ensureUserRecord($from);
    }
}
