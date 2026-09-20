<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\NewGame;

use BeachVolleybot\Telegram\MessageBuilders\NewGame\NewGameDatePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class NewGameDatePageProcessor extends AbstractNewGameStepProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $text = $callbackQuery->message->text;

        $picker = new NewGameDatePickerMessageBuilder($this->translator($callbackQuery))
            ->build($this->callbackData->getPage(), $this->parsePlayersPerNet($text));

        $this->editWizard($callbackQuery, $picker);
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
