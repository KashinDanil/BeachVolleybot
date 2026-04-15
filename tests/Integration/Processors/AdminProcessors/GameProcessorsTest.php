<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\AdminProcessors;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\AdminProcessors\AdminAddNetProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminAddVolleyballProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\AdminGameDetailCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminGamesListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminPlayerSettingsProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminPlayersListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminRemoveLocationCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminRemoveNetProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminRemoveSlotProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminRemoveVolleyballProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class GameProcessorsTest extends ProcessorTestCase
{
    // --- GamesListProcessor ---

    public function testGamesListEditsMessage(): void
    {
        $this->seedFullGame();

        $callbackData = AdminCallbackData::create(AdminCallbackAction::GamesList)->withPage(1);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminGamesListCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    public function testGamesListShowsEmptyWhenNoGames(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::GamesList)->withPage(1);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminGamesListCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- GameDetailProcessor ---

    public function testGameDetailEditsMessage(): void
    {
        $gameId = $this->seedGameWithPlayer();

        $callbackData = AdminCallbackData::create(AdminCallbackAction::GameDetail)->withGameId($gameId);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminGameDetailCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    public function testGameDetailHandlesNonexistentGame(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::GameDetail)->withGameId(99999);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminGameDetailCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- PlayersListProcessor ---

    public function testPlayersListEditsMessage(): void
    {
        $gameId = $this->seedGameWithPlayer();

        $callbackData = AdminCallbackData::create(AdminCallbackAction::GamePlayers)->withGameId($gameId)->withPage(1);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminPlayersListCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- PlayerSettingsProcessor ---

    public function testPlayerSettingsEditsMessage(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::PlayerSettings)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminPlayerSettingsProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- GameRemoveSlotProcessor ---

    public function testRemoveSlotRemovesHighestPosition(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, position: 1);
        $this->createSlot($gameId, 200, 2);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::RemoveSlot)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminRemoveSlotProcessor($this->telegramSender, $callbackData)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int)$slots[0]['position']);
    }

    public function testRemoveSlotDeletesGamePlayerWhenLastSlot(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, position: 1);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::RemoveSlot)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminRemoveSlotProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertNull(new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200));
    }

    public function testRemoveSlotRefreshesInlineMessage(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, position: 1);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::RemoveSlot)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminRemoveSlotProcessor($this->telegramSender, $callbackData)->process($update);

        $editCalls = array_filter($this->bot->calls, fn($c) => 'editMessageText' === $c['method']);
        $this->assertGreaterThanOrEqual(1, count($editCalls));
    }

    // --- GameRemoveLocationProcessor ---

    public function testRemoveLocationClearsLocation(): void
    {
        $gameId = $this->seedFullGame();
        $this->db->update('games', ['location' => '55.7,37.6'], ['game_id' => $gameId]);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::RemoveLocation)->withGameId($gameId);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminRemoveLocationCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $game = $this->db->get('games', '*', ['game_id' => $gameId]);
        $this->assertNull($game['location']);
    }

    // --- GameAddNetProcessor ---

    public function testAddNetIncrementsNetCount(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, net: 0);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::AddNet)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminAddNetProcessor($this->telegramSender, $callbackData)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(1, (int)$gamePlayer['net']);
    }

    // --- GameRemoveNetProcessor ---

    public function testRemoveNetDecrementsNetCount(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, net: 2);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::RemoveNet)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminRemoveNetProcessor($this->telegramSender, $callbackData)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(1, (int)$gamePlayer['net']);
    }

    // --- GameAddVolleyballProcessor ---

    public function testAddVolleyballIncrementsCount(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 0);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::AddVolleyball)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminAddVolleyballProcessor($this->telegramSender, $callbackData)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(1, (int)$gamePlayer['volleyball']);
    }

    // --- GameRemoveVolleyballProcessor ---

    public function testRemoveVolleyballDecrementsCount(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 3);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::RemoveVolleyball)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminRemoveVolleyballProcessor($this->telegramSender, $callbackData)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(2, (int)$gamePlayer['volleyball']);
    }

    // --- RemoveSlot edge case: player not joined ---

    public function testRemoveSlotAnswersNotJoinedWhenPlayerHasNoSlots(): void
    {
        $gameId = $this->seedFullGame();

        $callbackData = AdminCallbackData::create(AdminCallbackAction::RemoveSlot)->withGameId($gameId)->withUserId(999);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminRemoveSlotProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertAnsweredWith('No slots to remove');
    }

    // --- PlayerSettings edge case: player not found ---

    public function testPlayerSettingsShowsPlayerNotFound(): void
    {
        $gameId = $this->seedFullGame();

        $callbackData = AdminCallbackData::create(AdminCallbackAction::PlayerSettings)->withGameId($gameId)->withUserId(999);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminPlayerSettingsProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- GameDetail edge case: with location ---

    public function testGameDetailShowsRemoveLocationWhenLocationExists(): void
    {
        $gameId = $this->seedGameWithPlayer();
        $this->db->update('games', ['location' => '55.7,37.6'], ['game_id' => $gameId]);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::GameDetail)->withGameId($gameId);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminGameDetailCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- GamesListProcessor: pagination ---

    public function testGamesListPaginationSecondPage(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->createGame(title: "Game $i", inlineMessageId: "msg_$i", inlineQueryId: "q_$i");
        }

        $callbackData = AdminCallbackData::create(AdminCallbackAction::GamesList)->withPage(2);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminGamesListCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- RemoveNet edge case: player has zero nets ---

    public function testRemoveNetWithZeroNetsDoesNotDecrementBelowZero(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, net: 0);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::RemoveNet)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminRemoveNetProcessor($this->telegramSender, $callbackData)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(0, (int)$gamePlayer['net']);
    }

    // --- RemoveVolleyball edge case: player has zero volleyballs ---

    public function testRemoveVolleyballWithZeroDoesNotDecrementBelowZero(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 0);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::RemoveVolleyball)->withGameId($gameId)->withUserId(200);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminRemoveVolleyballProcessor($this->telegramSender, $callbackData)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(0, (int)$gamePlayer['volleyball']);
    }

    // --- PlayersListProcessor: handles nonexistent game ---

    public function testPlayersListHandlesNonexistentGame(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::GamePlayers)->withGameId(99999)->withPage(1);
        $update = TelegramUpdate::fromArray(
            $this->adminCallbackQueryPayload($callbackData->toJson()),
        );

        new AdminPlayersListCallbackProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }
}
