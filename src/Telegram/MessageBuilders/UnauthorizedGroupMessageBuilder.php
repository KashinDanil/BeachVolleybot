<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

final class UnauthorizedGroupMessageBuilder extends AbstractMessageBuilder
{
    public const string NOTICE = '🚫 The bot is not authorized in this group.';

    public function __construct(
        private readonly Translator $translator = new Translator(),
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
        return $this->formatter->escape(
            $this->translator->translate(self::NOTICE),
        );
    }
}
