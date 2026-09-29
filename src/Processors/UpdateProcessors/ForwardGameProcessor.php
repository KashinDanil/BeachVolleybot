<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\Validator\Rules\Game\GameCreatorOrAdminRule;
use BeachVolleybot\Validator\Validator;

class ForwardGameProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $result = $update->chosenInlineResult;
        $gameId = ForwardGameQueryExtractor::extract($result->query);

        if (null === $gameId) {
            return;
        }

        $gameManager = new GameManager();
        $gameRecord = $gameManager->findGameRecordById($gameId);

        if (null === $gameRecord) {
            return;
        }

        $user = new UserManager()->ensureUserRecord($result->from);
        $validationState = new Validator(
            [
                new GameCreatorOrAdminRule(
                    $result->from->id,
                    $gameRecord->createdBy,
                    $user->role->isAdmin(),
                ),
            ]
        )->validate();

        if (!$validationState->isSuccess()) {
            return;
        }

        $gameManager->addInlineMessage($gameId, $result->inlineMessageId, $result->resultId);
        $this->logUserAction($result->from, 'forward_game', "gameId=$gameId");
    }
}
