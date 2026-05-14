<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Processors\ProcessorRegistry;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

readonly class IncomingMessageQueueRouter
{
    /** @var class-string<QueueInterface> */
    private string $queueClass;

    /**
     * @param class-string<QueueInterface> $queueClass
     */
    public function __construct(
        string $queueClass,
        private string $baseDir,
        private ProcessorRegistry $registry,
    ) {
        $this->queueClass = $queueClass;
    }

    public function route(TelegramUpdate $update): void
    {
        $queueName = $this->registry->resolveQueueName($update);

        if (null === $queueName) {
            Logger::logVerbose('No handler matched update, skipping' . PHP_EOL);

            return;
        }

        $queue = new ($this->queueClass)($queueName, $this->baseDir);
        $queue->enqueue(new QueueMessage($update->jsonSerialize()));
    }
}
