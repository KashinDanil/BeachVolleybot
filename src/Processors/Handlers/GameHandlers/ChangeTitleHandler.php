<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\GameHandlers;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ChangeTitleProcessor;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\CurrentUser;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use BeachVolleybot\Validator\Rules\GameCreatorOnlyRule;
use BeachVolleybot\Validator\Rules\GameCreatorOrAdminRule;
use BeachVolleybot\Validator\Rules\KickoffDayInTheFutureRule;

final readonly class ChangeTitleHandler extends AbstractGameReplyQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $this->repliesToGameMessage($update->message)
            && $update->message->hasText()
            && $this->isAllowedRename($update->message);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new ChangeTitleProcessor($telegramSender);
    }

    private function isAllowedRename(TelegramMessage $message): bool
    {
        $newTitle = $message->text ?? '';

        if (!$this->hasDateAndTime($newTitle)) {
            return false;
        }

        $gameRecord = $this->resolveGameRecord($message);

        if (null === $gameRecord) {
            return false;
        }

        return $this->isKickoffDayInTheFuture($newTitle, $gameRecord)
            && $this->isAllowedAuthor($message, $gameRecord);
    }

    private function hasDateAndTime(string $newTitle): bool
    {
        return new DateTimeInTitleRule($newTitle)->isValid();
    }

    private function isKickoffDayInTheFuture(string $newTitle, GameRecord $gameRecord): bool
    {
        return new KickoffDayInTheFutureRule($newTitle, $gameRecord->createdAt)->isValid();
    }

    private function isAllowedAuthor(TelegramMessage $message, GameRecord $gameRecord): bool
    {
        $from = $message->from;

        if (!$message->chat->isPrivate()) {
            return new GameCreatorOnlyRule($from->id, $gameRecord->createdBy)->isValid();
        }

        $isAdmin = CurrentUser::fromTelegramId($from->id)->isAdmin();

        return new GameCreatorOrAdminRule($from->id, $gameRecord->createdBy, $isAdmin)->isValid();
    }

    private function resolveGameRecord(TelegramMessage $message): ?GameRecord
    {
        $gameKey = GameCallbackData::extractGameKey($message->replyToMessage);

        if (null === $gameKey) {
            return null;
        }

        return new GameManager()->findGameRecordByGameKey($gameKey);
    }
}
