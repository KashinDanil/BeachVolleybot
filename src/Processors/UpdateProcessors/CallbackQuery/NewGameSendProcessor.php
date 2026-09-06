<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\GameKey;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameMessagePinner;
use BeachVolleybot\Game\GameMessagePoster;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Game\ShareGameReplySender;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGameCreatedMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\NewGameFormText;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\PlainText;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;

class NewGameSendProcessor extends AbstractVenueSelectionStepProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $wizardMessage = $callbackQuery->message;

        $text = $wizardMessage->text;

        if (!$this->passesValidation($callbackQuery, ...$this->selectionRules($text))) {
            return;
        }

        $date = $this->parseDate($text);
        $time = $this->parseTime($text);
        $venue = $this->resolveVenue();

        $chatId = $wizardMessage->chat->id;
        $gameKey = $this->resolveGameKey($wizardMessage, $chatId);
        $gameManager = new GameManager();

        if (null !== $gameManager->resolveGameIdByGameKey($gameKey)) {
            $this->answerCallbackQuery($callbackQuery, '');

            return;
        }

        $translator = Translator::fromUser($callbackQuery->from);
        $title = new NewGameFormText($translator, new PlainText())
            ->buildGameTitle($date, $time, $venue?->name);
        $newGameData = NewGameData::fromUser($callbackQuery->from, $title, $gameKey);

        $postedGame = new GameMessagePoster($this->telegramSender, $gameManager)
            ->post($newGameData, $chatId, $wizardMessage->resolveMessageThreadId());

        if (null === $postedGame) {
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::SOMETHING_WENT_WRONG);

            return;
        }

        $this->editWizard($callbackQuery, new NewGameCreatedMessageBuilder($translator)->build());
        $this->answerCallbackQuery($callbackQuery, '');

        new GameMessagePinner($this->telegramSender)
            ->pinGameMessageIfGroup($wizardMessage->chat, $postedGame, $newGameData->title, $wizardMessage->date);

        new ShareGameReplySender($this->telegramSender)->sendInDm($wizardMessage->chat, $postedGame->sentMessageId, $postedGame->gameId, $callbackQuery->from);

        new WeatherEnqueuer()->enqueue($postedGame->gameId);
        $this->logUserAction($callbackQuery->from, 'create_game_from_new_game_wizard', "gameId={$postedGame->gameId}");
    }

    private function resolveGameKey(TelegramMessage $wizardMessage, int $chatId): string
    {
        if ($wizardMessage->isEphemeral()) {
            return GameKey::fromEphemeral($chatId, $wizardMessage->ephemeralMessageId);
        }

        return GameKey::fromMessage($chatId, $wizardMessage->messageId);
    }
}
