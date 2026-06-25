<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

final class UnauthorizedGameMessageBuilder extends AbstractMessageBuilder
{
    public function __construct(
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
    }

    public function build(): TelegramMessage
    {
        return new TelegramMessage(
            new Text($this->buildText(), $this->formatter->parseMode(), self::DISABLE_PREVIEW),
        );
    }

    private function buildText(): string
    {
        return $this->formatter->escape('This game has turned into a pumpkin 🎃 because this is an ')
            . $this->formatter->bold('unauthorized use')
            . $this->formatter->escape(' of the bot');
    }
}
