<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;

/**
 * Shared base for the /new_game wizard pages. The wizard stores no state, so every button
 * it hands out carries the chosen language for the step behind the next tap to read back.
 */
abstract class AbstractNewGameMessageBuilder extends AbstractMessageBuilder
{
    protected readonly NewGameFormText $formText;

    public function __construct(
        protected readonly Translator $translator,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
        $this->formText = new NewGameFormText($translator, $this->formatter);
    }

    protected function callbackData(NewGameCallbackAction $action): NewGameCallbackData
    {
        return NewGameCallbackData::create($action)->withLanguage($this->translator->language());
    }
}
