<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Factories\UserGameDetailMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Validator\Rules\GameCreatorOnlyRule;

class UserGameDetailCallbackProcessor extends AbstractCallbackProcessor
{
    public function __construct(
        TelegramMessageSender $telegramSender,
        private readonly UserCallbackData $callbackData,
    ) {
        parent::__construct($telegramSender);
    }

    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $gameId = $this->callbackData->getGameId();

        if (null === $gameId) {
            $this->answerCallbackQuery($callbackQuery, '');

            return;
        }

        $gameRecord = new GameManager()->findGameRecordById($gameId);

        if (null === $gameRecord) {
            $this->answerCallbackQuery($callbackQuery, '');

            return;
        }

        $creatorRule = new GameCreatorOnlyRule($callbackQuery->from->id, $gameRecord->createdBy);

        if (false === $creatorRule->isValid()) {
            $this->answerCallbackQuery($callbackQuery, '');

            return;
        }

        $message = UserGameDetailMessageFactory::build(
            gameRecord: $gameRecord,
            listPage: $this->callbackData->getPage(),
            translator: Translator::fromUser($callbackQuery->from),
        );

        $this->telegramSender->editMessage(
            $callbackQuery->message->chat->id,
            $callbackQuery->message->messageId,
            $message,
        );
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
