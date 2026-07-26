<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGameTimePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\DateInTheFutureRule;
use BeachVolleybot\Validator\Rules\SelectedDateRule;
use DateTimeImmutable;

class NewGamePickDateProcessor extends AbstractNewGameStepProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $rawDate = $this->callbackData->getDate();

        if (!$this->passesValidation($callbackQuery, new SelectedDateRule($rawDate))) {
            return;
        }

        $date = new DateTimeImmutable($rawDate);
        if (!$this->passesValidation($callbackQuery, new DateInTheFutureRule($date, new DateTimeImmutable()))) {
            return;
        }

        $picker = new NewGameTimePickerMessageBuilder(Translator::fromUser($callbackQuery->from))
            ->build($date, NewGameTimePickerMessageBuilder::START_PAGE);

        $this->editWizard($callbackQuery, $picker);
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
