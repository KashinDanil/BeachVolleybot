<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use BeachVolleybot\Validator\Rules\GameCreatorOnlyRule;
use BeachVolleybot\Validator\Rules\KickoffDayInTheFutureRule;
use BeachVolleybot\Validator\Validator;

class ChangeTitleProcessor extends AbstractGameReplyProcessor
{
    protected function handle(TelegramUpdate $update, GameRecord $gameRecord): void
    {
        $message = $update->message;
        $from = $message->from;
        $newTitle = $message->text ?? '';

        $validationState = new Validator([
            new GameCreatorOnlyRule($from->id, $gameRecord->createdBy),
            new KickoffDayInTheFutureRule($newTitle, $gameRecord->createdAt),
            new DateTimeInTitleRule($newTitle),
        ])->validate();

        if (!$validationState->isSuccess()) {
            return;
        }

        new GameManager()->changeTitle(
            $gameRecord->gameId,
            $from->id,
            $from->firstName,
            $from->lastName,
            $from->username,
            $newTitle,
        );
        $this->logUserAction($from, 'change_title', "gameId=$gameRecord->gameId;newTitle=$newTitle");

        $this->refreshInlineMessage($gameRecord->inlineMessageId);
        $this->deleteMessage($message);
    }
}
