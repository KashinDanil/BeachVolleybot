<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class UserGamesListCallbackProcessorTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    public function testEditsMessageOnPageChange(): void
    {
        $this->createGame(title: 'Game 1 18:00', createdBy: self::SENDER_ID, gameKey: 'q1');

        $this->processCallback(page: 1);

        $this->assertMessageEdited();
    }

    public function testListsOnlyGamesCreatedByTheSender(): void
    {
        $myGameId = $this->createGame(title: 'Mine 18:00', createdBy: self::SENDER_ID, gameKey: 'q_mine', inlineMessageId: 'msg_mine');
        $this->createGame(title: 'Other 18:00', createdBy: 999, gameKey: 'q_other', inlineMessageId: 'msg_other');

        $this->processCallback(page: 1);

        $keyboard = $this->extractEditedKeyboard();
        $this->assertCount(1, $keyboard, 'Expected only the sender-owned game to appear');

        $callbackData = UserCallbackData::fromJson($keyboard[0][0]['callback_data']);
        $this->assertSame($myGameId, $callbackData->getGameId());
    }

    public function testPageTwoShowsDifferentGamesThanPageOne(): void
    {
        $gameIds = [];

        for ($index = 1; $index <= 7; $index++) {
            $gameIds[] = $this->createGame(
                title: "Game $index 18:00",
                createdBy: self::SENDER_ID,
                gameKey: "q_$index",
                inlineMessageId: "msg_$index",
            );
        }

        $this->processCallback(page: 2);
        $keyboard = $this->extractEditedKeyboard();

        // 7 games, page size 5 → page 2 shows the 2 oldest (game IDs 1, 2 in DESC order: 2, 1)
        $gameRows = array_slice($keyboard, 0, -1); // strip pagination row
        $this->assertCount(2, $gameRows);

        $firstShown  = UserCallbackData::fromJson($gameRows[0][0]['callback_data']);
        $secondShown = UserCallbackData::fromJson($gameRows[1][0]['callback_data']);
        $this->assertSame($gameIds[1], $firstShown->getGameId());
        $this->assertSame($gameIds[0], $secondShown->getGameId());
    }

    public function testEmptyListStillEditsToEmptyState(): void
    {
        $this->processCallback(page: 1);

        $editCall = $this->lastEditMessageCall();
        $this->assertNotNull($editCall);
        $this->assertStringContainsString("You haven't created any games yet", $editCall['args'][2]);
    }

    public function testAnswersCallbackQuery(): void
    {
        $this->processCallback(page: 1);

        $answerCalls = array_filter($this->bot->calls, fn($call) => 'answerCallbackQuery' === $call['method']);
        $this->assertNotEmpty($answerCalls, 'Expected answerCallbackQuery to be called');
    }

    private function processCallback(int $page): void
    {
        $callbackData = UserCallbackData::create(UserCallbackAction::GamesList)->withPage($page);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload(
                data: $callbackData->toJson(),
                fromId: self::SENDER_ID,
                chatId: self::SENDER_ID,
            ),
        );

        new UserGamesListCallbackProcessor($this->telegramSender, $callbackData)->process($update);
    }

    private function lastEditMessageCall(): ?array
    {
        $calls = array_filter($this->bot->calls, fn($call) => 'editMessageText' === $call['method']);

        if (empty($calls)) {
            return null;
        }

        return end($calls);
    }

    private function extractEditedKeyboard(): array
    {
        $editCall = $this->lastEditMessageCall();
        $this->assertNotNull($editCall);

        return json_decode($editCall['args'][5]->toJson(), true)['inline_keyboard'];
    }
}
