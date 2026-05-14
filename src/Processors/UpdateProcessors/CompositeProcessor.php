<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final class CompositeProcessor extends AbstractActionProcessor
{
    /** @var AbstractActionProcessor[] */
    private readonly array $processors;

    public function __construct(
        TelegramMessageSender $telegramSender,
        AbstractActionProcessor ...$processors,
    ) {
        parent::__construct($telegramSender);
        $this->processors = $processors;
    }

    public function process(TelegramUpdate $update): void
    {
        foreach ($this->processors as $processor) {
            $processor->process($update);
        }
    }
}
