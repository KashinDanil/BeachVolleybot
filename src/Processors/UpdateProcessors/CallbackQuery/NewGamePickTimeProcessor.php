<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGameLocationPickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\ResolvableDateRule;
use BeachVolleybot\Validator\Rules\SelectedTimeRule;
use DateTimeImmutable;

class NewGamePickTimeProcessor extends AbstractNewGameStepProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $text = $callbackQuery->message->text;
        $time = $this->callbackData->getTime();

        $isValid = $this->passesValidation(
            $callbackQuery,
            new ResolvableDateRule($text, new DateTimeImmutable()),
            new SelectedTimeRule($time),
        );

        if (!$isValid) {
            return;
        }

        $picker = new NewGameLocationPickerMessageBuilder(Translator::fromUser($callbackQuery->from))
            ->build($this->parseDate($text), $time);

        $this->editWizard($callbackQuery, $picker);
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
