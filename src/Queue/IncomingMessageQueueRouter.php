<?php

declare(strict_types=1);

namespace BeachVolleybot\Queue;

use BeachVolleybot\Common\Logger;
use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

readonly class IncomingMessageQueueRouter
{
    private const string NEW_GAME_COMMAND = '/new_game';
    private const string NEW_GAME_QUEUE   = 'new_game';

    private const string EDIT_GAME_COMMAND_PREFIX = '/eg';
    private const string EDIT_GAME_QUEUE_PREFIX   = 'edit_game_';

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
        if (isset($message['reply_to_message']['via_bot'])) {
            return self::EDIT_GAME_QUEUE_PREFIX . $message['reply_to_message']['message_id'];
        }

        $text = $message['text'] ?? null;

        if (null === $text || !str_starts_with($text, '/')) {
            return $this->skip('Not a command message', $message);
        }

        if (str_starts_with($text, self::NEW_GAME_COMMAND)) {
            return self::NEW_GAME_QUEUE;
        }

        return $this->skip('Unrecognized command', $message);
    }

    private function resolveCallbackQueryQueue(array $callbackQuery): ?string
    {
        $data = $callbackQuery['data'] ?? null;
        if (null === $data) {
            return $this->skip('Callback query missing data', $callbackQuery);
        }

        $messageId = $callbackQuery['message']['message_id']
            ?? $callbackQuery['inline_message_id']
            ?? null;
        if (null === $messageId) {
            return $this->skip('Callback query missing message_id and inline_message_id', $callbackQuery);
        }

        if (str_starts_with($data, self::EDIT_GAME_COMMAND_PREFIX)) {
            return self::EDIT_GAME_QUEUE_PREFIX . $messageId;
        }

        return $this->skip('Unrecognized callback command', $callbackQuery);
    }

    private function skip(string $reason, array $context): null
    {
        Logger::logApp($reason . ', skipping: ' . json_encode($context));

        return null;
    }
}