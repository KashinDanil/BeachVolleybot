<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UserProcessors;

use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserGamesListCommandHandler;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCommandProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

final class UserGamesListCommandProcessorTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    public function testEmptyListShowsEmptyStateText(): void
    {
        $this->processCommand();

        $sendCall = $this->lastSendMessageCall();
        $this->assertNotNull($sendCall, 'Expected sendMessage to be called');
        $this->assertSame(self::SENDER_ID, $sendCall['args'][0]);
        $this->assertStringContainsString("You haven't created any games yet", $sendCall['args'][1]);
    }

    public function testEmptyListHasNoButtons(): void
    {
        $this->processCommand();

        $keyboard = $this->extractKeyboard($this->lastSendMessageCall());
        $this->assertSame([], $keyboard);
    }

    public function testDeletesTheGamesCommandMessage(): void
    {
        $this->processCommand();

        $deleteCalls = array_filter($this->bot->calls, fn($call) => 'deleteMessage' === $call['method']);
        $this->assertCount(1, $deleteCalls);

        $deleteCall = end($deleteCalls);
        $this->assertSame(self::SENDER_ID, $deleteCall['args'][0]); // chatId
        $this->assertSame(109, $deleteCall['args'][1]);             // messageId from privateMessagePayload
    }

    public function testListsOnlyGamesCreatedByTheSender(): void
    {
        $myGameId = $this->createGame(title: 'Mine 18:00',  createdBy: self::SENDER_ID, gameKey: 'q_mine',  inlineMessageId: 'msg_mine');
        $this->createGame(title: 'Other 18:00', createdBy: 999, gameKey: 'q_other', inlineMessageId: 'msg_other');

        $this->processCommand();

        $keyboard = $this->extractKeyboard($this->lastSendMessageCall());
        $this->assertCount(1, $keyboard, 'Expected one game row only');

        $callbackData = UserCallbackData::fromJson($keyboard[0][0]['callback_data']);
        $this->assertSame($myGameId, $callbackData->getGameId(), 'Listed game must be the sender-owned one');
    }

    public function testGameButtonCallbackContainsGameIdAndPageOne(): void
    {
        $gameId = $this->createGame(title: 'Mine 18:00', createdBy: self::SENDER_ID, gameKey: 'q_mine');

        $this->processCommand();

        $keyboard = $this->extractKeyboard($this->lastSendMessageCall());
        $callbackData = UserCallbackData::fromJson($keyboard[0][0]['callback_data']);

        $this->assertNotNull($callbackData);
        $this->assertSame(UserCallbackAction::GameDetail, $callbackData->getAction());
        $this->assertSame($gameId, $callbackData->getGameId());
        $this->assertSame(1, $callbackData->getPage());
    }

    public function testPaginationAppearsWhenMoreThanFivePages(): void
    {
        for ($index = 1; $index <= 6; $index++) {
            $this->createGame(
                title: "Game $index 18:00",
                createdBy: self::SENDER_ID,
                gameKey: "q_$index",
                inlineMessageId: "msg_$index",
            );
        }

        $this->processCommand();

        $keyboard = $this->extractKeyboard($this->lastSendMessageCall());
        $this->assertCount(6, $keyboard, '5 game rows + 1 pagination row');

        $paginationRow = end($keyboard);
        $this->assertCount(1, $paginationRow, 'First page only shows Next');
        $nextCallback = UserCallbackData::fromJson($paginationRow[0]['callback_data']);
        $this->assertSame(UserCallbackAction::GamesList, $nextCallback->getAction());
        $this->assertSame(2, $nextCallback->getPage());
    }

    private function processCommand(): void
    {
        $update = TelegramUpdate::fromArray(
            $this->privateMessagePayload(UserGamesListCommandHandler::COMMAND, fromId: self::SENDER_ID),
        );

        new UserGamesListCommandProcessor($this->telegramSender)->process($update);
    }

    private function lastSendMessageCall(): ?array
    {
        $calls = array_filter($this->bot->calls, fn($call) => 'sendMessage' === $call['method']);

        if (empty($calls)) {
            return null;
        }

        return end($calls);
    }

    private function extractKeyboard(?array $sendCall): array
    {
        $this->assertNotNull($sendCall);
        /** @var InlineKeyboardMarkup $keyboard */
        $keyboard = $sendCall['args'][5];

        return json_decode($keyboard->toJson(), true)['inline_keyboard'];
    }
}
