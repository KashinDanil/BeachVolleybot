<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\GameNotificationMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;
use Throwable;

final readonly class NotificationSender
{
    public function __construct(
        private TelegramMessageSender $telegramSender,
        private UserManager $userManager = new UserManager(),
        private GameUserManager $gameUserManager = new GameUserManager(),
    ) {
    }

    public function send(NotificationQueuePayload $notificationPayload, GameRecord $game): void
    {
        $recipient = $this->findEligibleRecipient($notificationPayload);

        if (null === $recipient) {
            return;
        }

        $message = $this->buildMessage($notificationPayload, $game, $recipient);
        $this->sendToRecipient($recipient, $notificationPayload, $message);
    }

    /** The payload's user, as long as they are still in the game and opted in to this type. */
    private function findEligibleRecipient(NotificationQueuePayload $notificationPayload): ?UserRecord
    {
        if (!$this->gameUserManager->isUserInGame($notificationPayload->gameId, $notificationPayload->userId)) {
            return null;
        }

        $user = $this->userManager->findUserRecordById($notificationPayload->userId);

        if (null === $user) {
            return null;
        }

        if (!$user->notifications->isEnabled($notificationPayload->type)) {
            return null;
        }

        return $user;
    }

    private function buildMessage(NotificationQueuePayload $notificationPayload, GameRecord $game, UserRecord $recipient): TelegramMessage
    {
        return new GameNotificationMessageBuilder(Translator::fromLanguageCode($recipient->languageCode))
            ->build($notificationPayload->type, $game);
    }

    private function sendToRecipient(UserRecord $recipient, NotificationQueuePayload $notificationPayload, TelegramMessage $message): void
    {
        try {
            $messageId = $this->telegramSender->sendMessage($recipient->telegramUserId, $message);
        } catch (Throwable $exception) {
            $this->logDeliveryFailure($recipient, $notificationPayload, $exception->getMessage());

            return;
        }

        if (0 === $messageId) {
            $this->logDeliveryFailure($recipient, $notificationPayload, 'see the sendMessage failure above');
        }
    }

    private function logDeliveryFailure(UserRecord $recipient, NotificationQueuePayload $notificationPayload, string $reason): void
    {
        Logger::logApp(
            sprintf(
                'Notification %s for game #%d not delivered to user %d: %s',
                $notificationPayload->type->name,
                $notificationPayload->gameId,
                $recipient->telegramUserId,
                $reason,
            )
        );
    }
}
