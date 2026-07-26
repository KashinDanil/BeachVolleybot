<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class NewGameCreatedMessageBuilder extends AbstractMessageBuilder
{
    private readonly NewGameFormText $formText;

    public function __construct(
        Translator $translator,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
        $this->formText = new NewGameFormText($translator, $this->formatter);
    }

    public function build(): TelegramMessage
    {
        return $this->buildMessage($this->formText->buildSuccess(), []);
    }
}
