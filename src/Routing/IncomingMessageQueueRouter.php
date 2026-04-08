<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

readonly class IncomingMessageQueueRouter
{
    private const string GAME_QUEUE_PREFIX = 'game_';
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
//        return null;
        if (isset($payload['chosen_inline_result'])) {
            $inlineMessageId = $payload['chosen_inline_result']['inline_message_id'] ?? null;
            if (null === $inlineMessageId) {
                return $this->skip('Chosen inline result missing inline_message_id', $payload);
            }

            return $this->gameQueueName($inlineMessageId);
        }

        if (isset($payload['callback_query'])) {
            return $this->resolveCallbackQueryQueue($payload['callback_query']);
        }

        if (isset($payload['message'])) {
            return $this->resolveMessageQueue($payload['message']);
        }

        return $this->skip('Unsupported payload format', $payload);
    }

    private function resolveMessageQueue(array $message): ?string
    {
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

        return $this->gameQueueName($inlineMessageId);
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

        if (null === $inlineMessageId) {
            return $this->skip('Callback query missing inline_message_id', $callbackQuery);
        }

        return $this->gameQueueName($inlineMessageId);
    }

    private function gameQueueName(string $inlineMessageId): string
    {
        return self::GAME_QUEUE_PREFIX . $this->sanitizeForFilesystem($inlineMessageId);
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