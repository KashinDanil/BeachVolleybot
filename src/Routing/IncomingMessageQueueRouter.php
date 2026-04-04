<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Common\Logger;
use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

readonly class IncomingMessageQueueRouter
{
    private const string GAME_QUEUE_PREFIX = 'game_';
    private const array ALLOWED_CHAT_TYPES = ['group'];

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

        if (!isset($message['reply_to_message']['via_bot'])) {
            return $this->skip('Not a reply to a via_bot message', $message);
        }

        return $this->gameQueueName($message['chat']['id'] . '_' . $message['reply_to_message']['message_id']);
    }

    private function resolveCallbackQueryQueue(array $callbackQuery): ?string
    {
        $inlineMessageId = $callbackQuery['inline_message_id'] ?? null;

        if (null === $inlineMessageId) {
            return $this->skip('Callback query missing inline_message_id', $callbackQuery);
        }

        return $this->gameQueueName($inlineMessageId);
    }

    private function gameQueueName(string $queueId): string
    {
        return self::GAME_QUEUE_PREFIX . $queueId;
    }

    private function skip(string $reason, array $context): null
    {
        Logger::logApp($reason . ', skipping: ' . json_encode($context));

        return null;
    }
}