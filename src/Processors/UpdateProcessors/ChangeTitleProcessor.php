<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use BeachVolleybot\Validator\Rules\GameCreatorOnlyRule;
use BeachVolleybot\Validator\Rules\KickoffInTheFutureRule;
use BeachVolleybot\Validator\Validator;

class ChangeTitleProcessor extends AbstractActionReplyProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;
        $from = $message->from;

        $inlineQueryId = CallbackData::extractInlineQueryId($message->replyToMessage);

        if (null === $inlineQueryId) {
            return;
        }

        $gameManager = new GameManager();
        $gameRecord = $gameManager->findGameRecord($inlineQueryId);

        if (null === $gameRecord) {
            return;
        }

        $newTitle = $message->text ?? '';

        $validationState = new Validator([
            new GameCreatorOnlyRule($from->id, $gameRecord->createdBy),
            new KickoffInTheFutureRule($gameRecord->title, $gameRecord->createdAt),
            new DateTimeInTitleRule($newTitle),
        ])->validate();

        if (!$validationState->isSuccess()) {
            return;
        }

        $gameManager->changeTitle(
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
