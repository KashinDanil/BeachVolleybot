<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramChat;
use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

readonly class IncomingMessageQueueRouter
{
    private const string GAME_QUEUE_PREFIX = 'game_';
    private const string DM_QUEUE_PREFIX = 'dm_';
    private const array ALLOWED_CHAT_TYPES = ['group', 'supergroup'];
    private const string CALLBACK_DATA_INLINE_QUERY_ID_KEY = 'q';

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

    public function route(array $payload): void
    {
        $queueName = $this->resolveQueueName($payload);

        if (null === $queueName) {
            return;
        }

        $queue = new ($this->queueClass)($queueName, $this->baseDir);
        $queue->enqueue(new QueueMessage($payload));
    }

    private function resolveQueueName(array $payload): ?string
    {
        if (isset($payload['chosen_inline_result'])) {
            $inlineMessageId = $payload['chosen_inline_result']['inline_message_id'] ?? null;
            if (null === $inlineMessageId) {
                return $this->skip('Chosen inline result missing inline_message_id', $payload);
            }

            return $this->inlineMessageQueueName($inlineMessageId);
        }

        if (isset($payload['callback_query'])) {
            return $this->resolveCallbackQueryQueue($payload['callback_query']);
        }

        if (isset($payload['message'])) {
            return $this->resolveMessageQueue($payload['message']);
        }

        // edited_message updates (e.g. live location) are intentionally not processed
        if (isset($payload['edited_message'])) {
            return null;
        }

        return $this->skip('Unsupported payload format', $payload);
    }

    private function resolveMessageQueue(array $message): ?string
    {
        if (TelegramChat::TYPE_PRIVATE === ($message['chat']['type'] ?? null)) {
            $userId = $message['from']['id'] ?? null;

            if (null === $userId) {
                return $this->skip('Private message missing from.id', $message);
            }

            return $this->dmQueueName((int)$userId);
        }

        if (!in_array($message['chat']['type'] ?? null, self::ALLOWED_CHAT_TYPES, true)) {
            return $this->skip('Not a group message', $message);
        }

        if (BOT_USERNAME !== ($message['reply_to_message']['via_bot']['username'] ?? null)) {
            return $this->skip('Not a reply to a message from this bot', $message);
        }

        $inlineQueryId = $this->extractInlineQueryIdFromMetaButton($message['reply_to_message']);

        if (null === $inlineQueryId) {
            return $this->skip('Meta-button missing inline_query_id', $message);
        }

        $inlineMessageId = new GameRepository(Connection::get())->findInlineMessageIdByInlineQueryId($inlineQueryId);

        if (null === $inlineMessageId) {
            return $this->skip('Game not found by inline_query_id: ' . $inlineQueryId, $message);
        }

        return $this->inlineMessageQueueName($inlineMessageId);
    }

    private function extractInlineQueryIdFromMetaButton(array $replyToMessage): ?string
    {
        $metaButtonCallbackData = $replyToMessage['reply_markup']['inline_keyboard'][0][0]['callback_data'] ?? null;

        if (null === $metaButtonCallbackData) {
            return null;
        }

        $decoded = json_decode($metaButtonCallbackData, true, 512, JSON_THROW_ON_ERROR);

        return $decoded[self::CALLBACK_DATA_INLINE_QUERY_ID_KEY] ?? null;
    }

    private function resolveCallbackQueryQueue(array $callbackQuery): ?string
    {
        $inlineMessageId = $callbackQuery['inline_message_id'] ?? null;

        if (null !== $inlineMessageId) {
            return $this->inlineMessageQueueName($inlineMessageId);
        }

        $userId = $callbackQuery['from']['id'] ?? null;

        if (null !== $userId) {
            return $this->dmQueueName((int)$userId);
        }

        return $this->skip('Callback query missing both inline_message_id and from.id', $callbackQuery);
    }

    private function inlineMessageQueueName(string $inlineMessageId): string
    {
        return self::GAME_QUEUE_PREFIX . $this->sanitizeForFilesystem($inlineMessageId);
    }

    private function dmQueueName(int $userId): string
    {
        return self::DM_QUEUE_PREFIX . $userId;
    }

    private function sanitizeForFilesystem(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $value);
    }

    private function skip(string $reason, array $context): null
    {
        Logger::logVerbose($reason . ', skipping: ' . json_encode($context) . PHP_EOL);

        return null;
    }
}