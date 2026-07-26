<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserGameDetailCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class UserGameDetailCallbackProcessorTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;
    private const int OTHER_USER_ID = 999;

    public function testEditsMessageWhenSenderIsCreator(): void
    {
        $gameId = $this->createGame(title: 'Mine 18:00', createdBy: self::SENDER_ID, gameKey: 'q_mine');

        $this->processCallback(gameId: $gameId, listPage: 1);

        $this->assertMessageEdited();
    }

    public function testDoesNotEditWhenSenderIsNotCreator(): void
    {
        $gameId = $this->createGame(title: 'Other 18:00', createdBy: self::OTHER_USER_ID, gameKey: 'q_other');

        $this->processCallback(gameId: $gameId, listPage: 1);

        $this->assertMessageNotEdited();
    }

    public function testDoesNotEditWhenGameDoesNotExist(): void
    {
        $this->processCallback(gameId: 99999, listPage: 1);

        $this->assertMessageNotEdited();
    }

    public function testDoesNotEditWhenGameIdIsMissing(): void
    {
        $callbackData = UserCallbackData::create(UserCallbackAction::GameDetail);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload(
                data: $callbackData->toJson(),
                fromId: self::SENDER_ID,
                chatId: self::SENDER_ID,
            ),
        );

        new UserGameDetailCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageNotEdited();
    }

    public function testAlwaysAnswersCallbackQuery(): void
    {
        $this->processCallback(gameId: 99999, listPage: 1);

        $answerCalls = array_filter($this->bot->calls, fn($call) => 'answerCallbackQuery' === $call['method']);
        $this->assertNotEmpty($answerCalls);
    }

    public function testBackButtonPreservesGivenListPage(): void
    {
        // Anchored to an explicit far-future date so the rendered keyboard always has the
        // future-game shape (Share + Back). A title like 'Mine 18:00' would resolve to today
        // at 18:00 and flip from future to past as the day progresses.
        $gameId = $this->createGame(title: 'Mine 31.12.2099 18:00', createdBy: self::SENDER_ID, gameKey: 'q_mine');

        $this->processCallback(gameId: $gameId, listPage: 4);

        $editCall = $this->lastEditMessageCall();
        $this->assertNotNull($editCall);

        $keyboard = json_decode($editCall['args'][5]->toJson(), true)['inline_keyboard'];
        $backButton = $keyboard[1][0];
        $backCallback = UserCallbackData::fromJson($backButton['callback_data']);
        $this->assertSame(UserCallbackAction::GamesList, $backCallback->getAction());
        $this->assertSame(4, $backCallback->getPage());
    }

    private function processCallback(int $gameId, int $listPage): void
    {
        $callbackData = UserCallbackData::create(UserCallbackAction::GameDetail)
            ->withGameId($gameId)
            ->withPage($listPage);

        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload(
                data: $callbackData->toJson(),
                fromId: self::SENDER_ID,
                chatId: self::SENDER_ID,
            ),
        );

        new UserGameDetailCallbackProcessor($this->telegramSender, $callbackData)->process($update);
    }

    private function lastEditMessageCall(): ?array
    {
        $calls = array_filter($this->bot->calls, fn($call) => 'editMessageText' === $call['method']);

        if (empty($calls)) {
            return null;
        }

        return end($calls);
    }
}
