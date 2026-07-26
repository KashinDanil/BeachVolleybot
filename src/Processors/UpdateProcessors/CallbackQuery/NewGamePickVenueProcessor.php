<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameKey;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameMessagePinner;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Game\NewGameFactory;
use BeachVolleybot\Game\ShareGameReplySender;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\MessageBuilders\NewGameCreatedMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\NewGameFormText;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\PlainText;
use BeachVolleybot\Validator\Rules\KnownVenueRule;
use BeachVolleybot\Validator\Rules\ResolvableDateRule;
use BeachVolleybot\Validator\Rules\ResolvableTimeRule;
use BeachVolleybot\Validator\Rules\RuleInterface;
use BeachVolleybot\Weather\Location\KnownVenues;
use BeachVolleybot\Weather\Location\Venue;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use DateTimeImmutable;

class NewGamePickVenueProcessor extends AbstractNewGameStepProcessor
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

        $title = new NewGameFormText(Translator::fromUser($callbackQuery->from), new PlainText())->buildGameTitle($date, $time, $venue?->name);
        $newGameData = NewGameData::fromUser($callbackQuery->from, $title, $gameKey);

        $sentMessageId = $this->telegramSender->sendMessage(
            $chatId,
            NewGameFactory::create($newGameData)->buildTelegramMessage(),
            $wizardMessage->resolveMessageThreadId(),
        );

        if (0 === $sentMessageId) {
            Logger::logApp('new_game: failed to post the game message to chat ' . $chatId);
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::SOMETHING_WENT_WRONG);

            return;
        }

        $gameId = $gameManager->createGame($newGameData);
        $gameManager->addChatMessage($gameId, $chatId, $sentMessageId);

        $this->editWizard(
            $callbackQuery,
            new NewGameCreatedMessageBuilder(Translator::fromUser($callbackQuery->from))->build(),
        );
        $this->answerCallbackQuery($callbackQuery, '');

        $this->pinInGroups($wizardMessage, $chatId, $sentMessageId, $newGameData->title);

        new ShareGameReplySender($this->telegramSender)->sendInDm($wizardMessage->chat, $sentMessageId, $gameId, $callbackQuery->from);

        new WeatherEnqueuer()->enqueue($gameId);
        $this->logUserAction($callbackQuery->from, 'create_game_from_new_game_wizard', "gameId=$gameId");
    }

    private function isSkip(): bool
    {
        return NewGameCallbackAction::SkipVenue === $this->callbackData->getAction();
    }

    private function resolveVenue(): ?Venue
    {
        $venueName = $this->callbackData->getVenueName();

        if (null === $venueName) {
            return null;
        }

        return KnownVenues::findByName($venueName);
    }

    /**
     * @return list<RuleInterface>
     */
    private function selectionRules(?string $text): array
    {
        $rules = [
            new ResolvableDateRule($text, new DateTimeImmutable()),
            new ResolvableTimeRule($text),
        ];

        if (!$this->isSkip()) {
            $rules[] = new KnownVenueRule($this->callbackData->getVenueName());
        }

        return $rules;
    }

    private function resolveGameKey(TelegramMessage $wizardMessage, int $chatId): string
    {
        if ($wizardMessage->isEphemeral()) {
            return GameKey::fromEphemeral($chatId, $wizardMessage->ephemeralMessageId);
        }

        return GameKey::fromMessage($chatId, $wizardMessage->messageId);
    }

    private function pinInGroups(TelegramMessage $wizardMessage, int $chatId, int $sentMessageId, string $title): void
    {
        if (!$wizardMessage->chat->isGroupChat()) {
            return;
        }

        new GameMessagePinner($this->telegramSender)->pinGameMessage($chatId, $sentMessageId, $title, $wizardMessage->date);
    }
}
