<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Common\BotMention;
use BeachVolleybot\Processors\AbstractQueuedProcessorHandler;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameFromMessageProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use BeachVolleybot\Validator\Validator;

/**
 * A plain message (in a group or a DM) that @mentions the bot and reads like a
 * game (has a date and time). The @mention both scopes the trigger and, in
 * groups, is what makes a privacy-mode bot receive the message at all. The
 * future-kickoff check is deferred to the processor so it stays out of the pure,
 * deterministic match.
 */
final readonly class CreateGameFromMessageHandler extends AbstractQueuedProcessorHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        if (!$update->hasMessage()) {
            return false;
        }

        $message = $update->message;

        return !$message->hasReplyToMessage()
            && $message->hasText()
            && BotMention::isPresent($message->text)
            && $this->hasDateAndTime($message->text);
    }

    public function routeToQueue(TelegramUpdate $update): ?string
    {
        return 'game_new_' . $update->message->chat->id;
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new CreateGameFromMessageProcessor($telegramSender);
    }

    private function hasDateAndTime(string $text): bool
    {
        $title = BotMention::strip($text);

        return new Validator([new DateTimeInTitleRule($title)])->validate()->isSuccess();
    }
}
