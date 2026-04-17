<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
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
        if ($update->hasChosenInlineResult()) {
            $inlineMessageId = $update->chosenInlineResult->inlineMessageId;

            if (null === $inlineMessageId) {
                return $this->skip('Chosen inline result missing inline_message_id');
            }

            return $this->inlineMessageQueueName($inlineMessageId);
        }

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

        if (!$message->chat->isGroupChat()) {
            return $this->skip('Not a group message');
        }

        if ($message->isViaThisBot()) {
            return $this->pinQueueName($message->chat->id);
        }

        if (!$message->replyToMessage?->isViaThisBot()) {
            return $this->skip('Not a reply to a message from this bot');
        }

        $inlineQueryId = CallbackData::extractInlineQueryId($message->replyToMessage);

        if (null === $inlineQueryId) {
            return $this->skip('Meta-button missing inline_query_id');
        }

        $inlineMessageId = new GameRepository(Connection::get())->findInlineMessageIdByInlineQueryId($inlineQueryId);

        if (null === $inlineMessageId) {
            return $this->skip('Game not found by inline_query_id: ' . $inlineQueryId);
        }

        return $this->inlineMessageQueueName($inlineMessageId);
    }

    private function resolveCallbackQueryQueue(TelegramCallbackQuery $callbackQuery): ?string
    {
        if ($callbackQuery->isInline()) {
            return $this->inlineMessageQueueName($callbackQuery->inlineMessageId);
        }

        return $this->dmQueueName($callbackQuery->from->id);
    }

    private function inlineMessageQueueName(string $inlineMessageId): string
    {
        return self::GAME_QUEUE_PREFIX . $this->sanitizeForFilesystem($inlineMessageId);
    }

    private function dmQueueName(int $userId): string
    {
        return self::DM_QUEUE_PREFIX . $userId;
    }

    private function pinQueueName(int $chatId): string
    {
        return self::PIN_QUEUE_PREFIX . $chatId;
    }

    private function sanitizeForFilesystem(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $value);
    }

    private function skip(string $reason): null
    {
        Logger::logVerbose($reason . ', skipping' . PHP_EOL);

        return null;
    }
}