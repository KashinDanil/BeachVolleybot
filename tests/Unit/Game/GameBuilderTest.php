<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\GameBuilder;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\GameSlotRecord;
use BeachVolleybot\Game\GameUserRecord;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Tests\Fixtures\CreatesGameMessageRecords;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameBuilderTest extends TestCase
{
    use CreatesGameMessageRecords;
    use CreatesUserRecords;

    // --- Game-level mapping ---

    public function testGameId(): void
    {
        $game = $this->buildGame(game: $this->gameRecord(gameId: 42));

        $this->assertSame(42, $game->getGameId());
    }

    public function testMessageTargets(): void
    {
        $targets = [$this->inlineGameMessageRecord('msg_abc'), $this->inlineGameMessageRecord('msg_xyz')];

        $game = $this->buildGame(messages: $targets);

        $this->assertEquals($targets, $game->getMessages());
    }

    public function testTitle(): void
    {
        $game = $this->buildGame(game: $this->gameRecord(title: 'Sunday Game 19:00'));

        $this->assertSame('Sunday Game 19:00', $game->getTitle());
    }

    public function testBuildTelegramMessageReturnsTelegramMessage(): void
    {
        $game = $this->buildGame(
            slots: [$this->gameSlotRecord()],
            gameUsers: [$this->gameUserRecord()],
            users: [$this->userRecord()],
        );

        $this->assertInstanceOf(TelegramMessage::class, $game->buildTelegramMessage());
    }

    // --- No slots ---

    public function testGameWithNoSlotsHasNoPlayers(): void
    {
        $game = $this->buildGame();

        $this->assertSame([], $game->getPlayers());
    }

    // --- Single player mapping ---

    public function testSinglePlayerNumber(): void
    {
        $game = $this->buildGame(
            slots: [$this->gameSlotRecord(position: 3)],
            gameUsers: [$this->gameUserRecord()],
            users: [$this->userRecord()],
        );

        $this->assertSame('3', $game->getPlayers()[0]->getPosition()->format());
    }

    public function testSinglePlayerVolleyballAndNet(): void
    {
        $game = $this->buildGame(
            slots: [$this->gameSlotRecord()],
            gameUsers: [$this->gameUserRecord(volleyball: 5, net: 2)],
            users: [$this->userRecord()],
        );

        $player = $game->getPlayers()[0];

        $this->assertSame(5, $player->getVolleyball());
        $this->assertSame(2, $player->getNet());
    }

    public function testSinglePlayerTime(): void
    {
        $game = $this->buildGame(
            slots: [$this->gameSlotRecord()],
            gameUsers: [$this->gameUserRecord(time: '19:30')],
            users: [$this->userRecord()],
        );

        $this->assertSame('19:30', $game->getPlayers()[0]->getTime());
    }

    public function testPlayerTimeMapsDefaultRowTime(): void
    {
        $game = $this->buildGame(
            slots: [$this->gameSlotRecord()],
            gameUsers: [$this->gameUserRecord()],
            users: [$this->userRecord()],
        );

        $this->assertSame('18:00', $game->getPlayers()[0]->getTime());
    }

    // --- Name composition ---

    public function testNameWithFirstAndLastName(): void
    {
        $game = $this->buildGame(
            slots: [$this->gameSlotRecord()],
            gameUsers: [$this->gameUserRecord()],
            users: [$this->userRecord(lastName: 'Smith')],
        );

        $this->assertSame('Alice Smith', $game->getPlayers()[0]->getName());
    }

    public function testNameWithFirstNameOnly(): void
    {
        $game = $this->buildGame(
            slots: [$this->gameSlotRecord()],
            gameUsers: [$this->gameUserRecord()],
            users: [$this->userRecord()],
        );

        $this->assertSame('Alice', $game->getPlayers()[0]->getName());
    }

    // --- Link ---

    public function testLinkBuiltFromUsername(): void
    {
        $game = $this->buildGame(
            slots: [$this->gameSlotRecord()],
            gameUsers: [$this->gameUserRecord()],
            users: [$this->userRecord(username: 'alice')],
        );

        $this->assertSame('https://t.me/alice', $game->getPlayers()[0]->getLink());
    }

    public function testLinkNullWhenUsernameNull(): void
    {
        $game = $this->buildGame(
            slots: [$this->gameSlotRecord()],
            gameUsers: [$this->gameUserRecord()],
            users: [$this->userRecord()],
        );

        $this->assertNull($game->getPlayers()[0]->getLink());
    }

    // --- Multiple players ---

    public function testMultiplePlayersOrderedBySlotPosition(): void
    {
        $game = $this->buildGame(
            slots: [
                $this->gameSlotRecord(),
                $this->gameSlotRecord(userId: 200, position: 2),
            ],
            gameUsers: [
                $this->gameUserRecord(),
                $this->gameUserRecord(userId: 200),
            ],
            users: [
                $this->userRecord(),
                $this->userRecord(telegramUserId: 200, firstName: 'Bob'),
            ],
        );

        $players = $game->getPlayers();

        $this->assertCount(2, $players);
        $this->assertSame('1', $players[0]->getPosition()->format());
        $this->assertSame('Alice', $players[0]->getName());
        $this->assertSame('2', $players[1]->getPosition()->format());
        $this->assertSame('Bob', $players[1]->getName());
    }

    // --- Multiple slots per user ---

    public function testUserWithMultipleSlotsCreatesSeparatePlayers(): void
    {
        $game = $this->buildGame(
            slots: [
                $this->gameSlotRecord(),
                $this->gameSlotRecord(position: 3),
            ],
            gameUsers: [
                $this->gameUserRecord(),
            ],
            users: [
                $this->userRecord(),
            ],
        );

        $players = $game->getPlayers();

        $this->assertCount(2, $players);
        $this->assertSame('1', $players[0]->getPosition()->format());
        $this->assertSame('3', $players[1]->getPosition()->format());
        $this->assertSame('Alice', $players[0]->getName());
        $this->assertSame('Alice', $players[1]->getName());
    }

    // --- Helpers ---

    private function gameRecord(
        int $gameId = 1,
        string $gameKey = 'query_1',
        string $title = 'Beach Game 18:00',
        string $createdAt = '2026-01-01 12:00:00',
        string $kickoffAt = '2099-12-31 18:00:00',
    ): GameRecord {
        return GameRecord::fromRow([
            'game_id' => $gameId,
            'game_key' => $gameKey,
            'created_by' => 100,
            'title' => $title,
            'created_at' => $createdAt,
            'kickoff_at' => $kickoffAt,
        ]);
    }

    private function gameSlotRecord(int $userId = 100, int $position = 1): GameSlotRecord
    {
        return new GameSlotRecord(
            gameId: 1,
            telegramUserId: $userId,
            position: $position,
            createdAt: new DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }

    private function gameUserRecord(
        int $userId = 100,
        int $volleyball = 0,
        int $net = 0,
        string $time = '18:00',
    ): GameUserRecord {
        return new GameUserRecord(
            gameId: 1,
            telegramUserId: $userId,
            time: $time,
            volleyball: $volleyball,
            net: $net,
            createdAt: new DateTimeImmutable('2026-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }

    private function buildGame(
        ?GameRecord $game = null,
        ?array $messages = null,
        array $slots = [],
        array $gameUsers = [],
        array $users = [],
    ): GameInterface {
        return new GameBuilder(
            gameRecord: $game ?? $this->gameRecord(),
            messages: $messages ?? [$this->inlineGameMessageRecord()],
            slots: $slots,
            gameUsers: $gameUsers,
            users: $users,
            addOns: [],
        )->build();
    }
}
