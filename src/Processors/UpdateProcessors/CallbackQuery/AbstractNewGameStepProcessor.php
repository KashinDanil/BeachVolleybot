<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Common\GameDateResolver;
use BeachVolleybot\Common\Logger;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramCallbackQuery;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Validator\Rules\RuleInterface;
use BeachVolleybot\Validator\Validator;
use DateTimeImmutable;

/**
 * Shared base for the /new_game wizard step processors. Holds the decoded
 * callback data and edits the wizard message on the correct surface — an
 * ephemeral message in a group, a normal message in a DM — so each step
 * processor stays surface-agnostic. The running selection is recovered by
 * re-parsing the wizard message text (parse-from-text state).
 */
abstract class AbstractNewGameStepProcessor extends AbstractCallbackProcessor
{
    public function __construct(
        TelegramMessageSender $telegramSender,
        protected readonly NewGameCallbackData $callbackData,
    ) {
        parent::__construct($telegramSender);
    }

    protected function editWizard(TelegramCallbackQuery $callbackQuery, TelegramMessage $message): void
    {
        $wizardMessage = $callbackQuery->message;

        if ($wizardMessage->isEphemeral()) {
            $this->telegramSender->editEphemeralMessage(
                $wizardMessage->chat->id,
                $wizardMessage->ephemeralMessageId,
                $callbackQuery->from->id,
                $message,
            );

            return;
        }

        $this->telegramSender->editMessage($wizardMessage->chat->id, $wizardMessage->messageId, $message);
    }

    protected function parseDate(?string $text): ?DateTimeImmutable
    {
        return GameDateResolver::resolve($text ?? '', new DateTimeImmutable());
    }

    protected function parseTime(?string $text): ?string
    {
        return TimeExtractor::extract($text ?? '');
    }

    protected function passesValidation(TelegramCallbackQuery $callbackQuery, RuleInterface ...$rules): bool
    {
        $state = new Validator($rules)->validate();

        if ($state->isSuccess()) {
            return true;
        }

        $this->abort($callbackQuery, 'new_game: ' . $state->getError()->getMessage());

        return false;
    }

    protected function abort(TelegramCallbackQuery $callbackQuery, string $reason): void
    {
        Logger::logApp($reason);
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::SOMETHING_WENT_WRONG);
    }
}
