<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Notifications\NotificationQueuePayload;
use BeachVolleybot\Notifications\NotificationSender;
use BeachVolleybot\Telegram\RateLimitedBotApi;
use BeachVolleybot\Telegram\TelegramMessageSender;
use DanilKashin\FileQueue\Queue\QueueMessage;

final readonly class NotificationQueueProcessor implements QueueProcessorInterface
{
    public function __construct(
        private NotificationSender $notificationSender = new NotificationSender(
            new TelegramMessageSender(new RateLimitedBotApi(TG_BOT_ACCESS_TOKEN, TG_MAX_REQUESTS_PER_SECOND)),
        ),
        private GameManager $gameManager = new GameManager(),
    ) {
    }

    public function process(QueueMessage $message): bool
    {
        $notificationPayload = NotificationQueuePayload::fromArray($message->payload);

        if (null === $notificationPayload) {
            Logger::logApp('Notification skipped: unrecognised payload ' . json_encode($message->payload));

            return true;
        }

        $game = $this->gameManager->findGameRecordById($notificationPayload->gameId);

        if (null === $game) {
            Logger::logVerbose('Notification skipped: game gone (id=' . $notificationPayload->gameId . ')');

            return true;
        }

        if (GameDateTimeResolver::isKickoffPast($game->kickoffAt)) {
            Logger::logVerbose('Notification skipped: game already kicked off (id=' . $notificationPayload->gameId . ')');

            return true;
        }

        $this->notificationSender->send($notificationPayload, $game);

        return true;
    }
}
