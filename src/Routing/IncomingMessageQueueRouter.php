<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramCallbackQuery;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

readonly class IncomingMessageQueueRouter
{
    private const string GAME_QUEUE_PREFIX = 'game_';
    private const string DM_QUEUE_PREFIX = 'dm_';
    private const string PIN_QUEUE_PREFIX = 'pin_';

    /** @var class-string<QueueInterface> */
    private string $queueClass;

    /**
     * @param class-string<QueueInterface> $queueClass
     */
    public function __construct(
        string $queueClass,
        private string $baseDir,
    ) {
        $this->queueClass = $queueClass;
    }

    public function route(TelegramUpdate $update): void
    {
        $queueName = $this->resolveQueueName($update);

        if (null === $queueName) {
            return;
        }

        $queue = new ($this->queueClass)($queueName, $this->baseDir);
        $queue->enqueue(new QueueMessage($update->jsonSerialize()));
    }

    private function resolveQueueName(TelegramUpdate $update): ?string
    {
        if ($update->hasCallbackQuery()) {
            return $this->resolveCallbackQueryQueue($update->callbackQuery);
        }

        if ($update->hasMessage()) {
            return $this->resolveMessageQueue($update->message);
        }

        if ($update->hasEditedMessage()) {
            return $this->resolveMessageQueue($update->editedMessage);
        }

        return $this->skip('Unsupported payload format');
    }

    private function resolveMessageQueue(TelegramMessage $message): ?string
    {
        if ($message->chat->isPrivate()) {
            return $this->dmQueueName($message->from->id);
        }

        if ($message->isViaThisBot() && $message->hasInlineKeyboard()) {
            return $this->pinQueueName($message->chat->id);
        }

        if ($message->isPinMessage() && $message->from->isThisBot()) {
            return $this->pinQueueName($message->chat->id);
        }

        if (!$message->replyToMessage?->isViaThisBot()) {
            return $this->skip('Not a reply to a message from this bot');
        }

        $inlineQueryId = CallbackData::extractInlineQueryId($message->replyToMessage);

        if (null === $inlineQueryId) {
            return $this->skip('Meta-button missing inline_query_id');
        }

        $gameId = new GameManager()->resolveGameIdByInlineQueryId($inlineQueryId);

        if (null === $gameId) {
            return $this->skip('Game not found by inline_query_id: ' . $inlineQueryId);
        }

        return $this->gameQueueName($gameId);
    }

    private function resolveCallbackQueryQueue(TelegramCallbackQuery $callbackQuery): ?string
    {
        if (!$callbackQuery->isInline()) {
            return $this->dmQueueName($callbackQuery->from->id);
        }

        $gameId = new GameManager()->resolveGameIdByInlineMessageId($callbackQuery->inlineMessageId);

        if (null === $gameId) {
            return $this->skip('Game not found by inline_message_id: ' . $callbackQuery->inlineMessageId);
        }

        return $this->gameQueueName($gameId);
    }

    private function gameQueueName(int $gameId): string
    {
        return self::GAME_QUEUE_PREFIX . $gameId;
    }

    private function dmQueueName(int $userId): string
    {
        return self::DM_QUEUE_PREFIX . $userId;
    }

    private function pinQueueName(int $chatId): string
    {
        return self::PIN_QUEUE_PREFIX . $chatId;
    }

    private function skip(string $reason): null
    {
        Logger::logVerbose($reason . ', skipping' . PHP_EOL);

        return null;
    }
}
