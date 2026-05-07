<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Common\Logger;
use BeachVolleybot\Common\RecentUpdateIdTracker;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ChangeTitleProcessor;
use BeachVolleybot\Processors\UpdateProcessors\DeletePinNotificationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\JoinWithTimeProcessor;
use BeachVolleybot\Processors\UpdateProcessors\PinMessageProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SendShareButtonProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLiveLocationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLocationProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCommandProcessor;
use BeachVolleybot\Processors\UserProcessors\UserStartCommandProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\RateLimitedBotApi;
use BeachVolleybot\Telegram\TelegramMessageSender;
use DanilKashin\FileQueue\Queue\QueueMessage;

readonly class AppQueueProcessor implements QueueProcessorInterface
{
    public function __construct(
        private RecentUpdateIdTracker $updateIdTracker = new RecentUpdateIdTracker(),
    ) {
    }

    public function process(QueueMessage $message): bool
    {
        $update = TelegramUpdate::fromArray($message->payload);

        if ($this->updateIdTracker->isTracked($update->updateId)) {
            Logger::logVerbose('Duplicate update skipped: ' . $update->updateId);

            return true;
        }

        $telegramSender = $this->createTelegramSender();

        $processor = $this->resolveProcessor($update, $telegramSender);

        if (null === $processor) {
            Logger::logVerbose('No processor found for update ' . $update->updateId);

            return false;
        }

        $processor->process($update);

        return true;
    }

    protected function createTelegramSender(): TelegramMessageSender
    {
        return new TelegramMessageSender(new RateLimitedBotApi(TG_BOT_ACCESS_TOKEN, TG_MAX_REQUESTS_PER_SECOND));
    }

    protected function resolveProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if ($update->hasMessage()) {
            return $this->resolveMessageProcessor($update, $telegramSender);
        }

        if ($update->hasEditedMessage()) {
            return new SetLiveLocationProcessor($telegramSender);
        }

        if ($update->hasCallbackQuery()) {
            return $this->resolveCallbackProcessor($update, $telegramSender);
        }

        return null;
    }

    private function resolveCallbackProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if ($update->callbackQuery->from->isAdmin()) {
            $adminCallback = AdminCallbackData::fromJson($update->callbackQuery->data);

            if (null !== $adminCallback) {
                return $adminCallback->getAction()->resolveProcessor($telegramSender, $adminCallback);
            }
        }

        $userCallback = UserCallbackData::fromJson($update->callbackQuery->data);

        if (null !== $userCallback) {
            return $userCallback->getAction()->resolveProcessor($telegramSender, $userCallback);
        }

        return CallbackData::fromJson($update->callbackQuery->data)?->getAction()->resolveProcessor($telegramSender);
    }

    private function resolveMessageProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if ($update->message->chat->isPrivate()) {
            return $this->resolvePrivateMessageProcessor($update, $telegramSender);
        }

        return $this->resolveGroupMessageProcessor($update, $telegramSender);
    }

    private function resolveGroupMessageProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if ($update->message->isPinMessage() && $update->message->from->isThisBot()) {
            return new DeletePinNotificationProcessor($telegramSender);
        }

        if ($update->message->isViaThisBot() && $update->message->hasInlineKeyboard()) {
            return new PinMessageProcessor($telegramSender);
        }

        return $this->resolveGameActionProcessor($update, $telegramSender);
    }

    private function resolvePrivateMessageProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if ($update->message->isViaThisBot() && $update->message->hasInlineKeyboard()) {
            return new SendShareButtonProcessor($telegramSender);
        }

        if (UserGamesListCommandProcessor::COMMAND === $update->message->text) {
            return new UserGamesListCommandProcessor($telegramSender);
        }

        if (SettingsMenuCallbackProcessor::COMMAND === $update->message->text && $update->message->from->isAdmin()) {
            return new SettingsMenuCallbackProcessor($telegramSender, AdminCallbackData::create(AdminCallbackAction::Settings));
        }

        if (UserStartCommandProcessor::COMMAND === $update->message->text) {
            return new UserStartCommandProcessor($telegramSender);
        }

        return $this->resolveGameActionProcessor($update, $telegramSender);
    }

    private function resolveGameActionProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if ($update->message->hasLocation()) {
            return new SetLocationProcessor($telegramSender);
        }

        if ($update->message->hasText()) {
            if (TimeExtractor::isTimeOnly($update->message->text)) {
                return new JoinWithTimeProcessor($telegramSender);
            }

            if ($update->message->hasReplyToMessage() && $update->message->replyToMessage->isViaThisBot()) {
                return new ChangeTitleProcessor($telegramSender);
            }
        }

        return null;
    }
}
