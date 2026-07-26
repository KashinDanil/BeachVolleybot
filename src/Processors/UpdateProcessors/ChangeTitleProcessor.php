<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\CurrentUser;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use BeachVolleybot\Validator\Rules\GameCreatorOnlyRule;
use BeachVolleybot\Validator\Rules\GameCreatorOrAdminRule;
use BeachVolleybot\Validator\Rules\KickoffDayInTheFutureRule;
use BeachVolleybot\Validator\Validator;

class ChangeTitleProcessor extends AbstractGameReplyProcessor
{
    protected function handle(TelegramUpdate $update, GameRecord $gameRecord): void
    {
        $message = $update->message;
        $from = $message->from;
        $newTitle = $message->text ?? '';

        $currentUser = CurrentUser::fromTelegramId($from->id);
        $authorRule = $message->chat->isPrivate()
            ? new GameCreatorOrAdminRule($from->id, $gameRecord->createdBy, $currentUser->isAdmin())
            : new GameCreatorOnlyRule($from->id, $gameRecord->createdBy);

        $validationState = new Validator([
            $authorRule,
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

        $this->refreshGameMessages($gameRecord->gameId);
        $this->deleteMessage($message);
    }
}
