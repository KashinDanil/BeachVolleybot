<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

final class UnauthorizedGameMessageBuilder extends AbstractMessageBuilder
{
    private const string PUMPKIN_INTRO    = 'This game has turned into a pumpkin 🎃 because this is an';
    private const string UNAUTHORIZED_USE = 'unauthorized use';
    private const string OF_THE_BOT       = 'of the bot';

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
        // Three parts so the middle can be bold; spacing is added here because translate() trims.
        return $this->formatter->escape($this->translator->translate(self::PUMPKIN_INTRO) . ' ')
            . $this->formatter->bold($this->translator->translate(self::UNAUTHORIZED_USE))
            . $this->formatter->escape(' ' . $this->translator->translate(self::OF_THE_BOT));
    }
}
