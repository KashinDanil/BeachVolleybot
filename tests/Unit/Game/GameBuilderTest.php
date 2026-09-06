<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\GameBuilder;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Targets\InlineGameMessageTarget;
use PHPUnit\Framework\TestCase;

final class GameBuilderTest extends TestCase
{
    // --- Game-level mapping ---

    public function testGameId(): void
    {
        $game = $this->buildGame(game: $this->gameRecord(gameId: 42));

        $this->assertSame(42, $game->getGameId());
    }

    public function testMessageTargets(): void
    {
        $targets = [new InlineGameMessageTarget('msg_abc'), new InlineGameMessageTarget('msg_xyz')];

        $game = $this->buildGame(messageTargets: $targets);

        $this->assertEquals($targets, $game->getMessageTargets());
    }

    public function testTitle(): void
    {
        $game = $this->buildGame(game: $this->gameRecord(title: 'Sunday Game 19:00'));

        $this->assertSame('Sunday Game 19:00', $game->getTitle());
    }

    public function testBuildTelegramMessageReturnsTelegramMessage(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gameUserRows: [$this->gameUserRow()],
            userRows: [$this->userRow()],
        );

        $this->assertInstanceOf(TelegramMessage::class, $game->buildTelegramMessage());
    }

    // --- No slots ---

    public function testGameWithNoSlotsHasEmptyUsers(): void
    {
        $game = $this->buildGame();

        $this->assertSame([], $game->getUsers());
    }

    // --- Single user mapping ---

    public function testSingleUserNumber(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow(position: 3)],
            gameUserRows: [$this->gameUserRow()],
            userRows: [$this->userRow()],
        );

        $this->assertSame('3', $game->getUsers()[0]->getNumber());
    }

    public function testSingleUserVolleyballAndNet(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gameUserRows: [$this->gameUserRow(volleyball: 5, net: 2)],
            userRows: [$this->userRow()],
        );

        $user = $game->getUsers()[0];

        $this->assertSame(5, $user->getVolleyball());
        $this->assertSame(2, $user->getNet());
    }

    public function testSingleUserTime(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gameUserRows: [$this->gameUserRow(time: '19:30')],
            userRows: [$this->userRow()],
        );

        $this->assertSame('19:30', $game->getUsers()[0]->getTime());
    }

    public function testUserTimeMapsDefaultRowTime(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gameUserRows: [$this->gameUserRow()],
            userRows: [$this->userRow()],
        );

        $this->assertSame('18:00', $game->getUsers()[0]->getTime());
    }

    // --- Name composition ---

    public function testNameWithFirstAndLastName(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gameUserRows: [$this->gameUserRow()],
            userRows: [$this->userRow(lastName: 'Smith')],
        );

        $this->assertSame('Alice Smith', $game->getUsers()[0]->getName());
    }

    public function testNameWithFirstNameOnly(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gameUserRows: [$this->gameUserRow()],
            userRows: [$this->userRow()],
        );

        $this->assertSame('Alice', $game->getUsers()[0]->getName());
    }

    // --- Link ---

    public function testLinkBuiltFromUsername(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gameUserRows: [$this->gameUserRow()],
            userRows: [$this->userRow(username: 'alice')],
        );

        $this->assertSame('https://t.me/alice', $game->getUsers()[0]->getLink());
    }

    public function testLinkNullWhenUsernameNull(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gameUserRows: [$this->gameUserRow()],
            userRows: [$this->userRow()],
        );

        $this->assertNull($game->getUsers()[0]->getLink());
    }

    // --- Multiple users ---

    public function testMultipleUsersOrderedBySlotPosition(): void
    {
        $game = $this->buildGame(
            slotRows: [
                $this->slotRow(),
                $this->slotRow(userId: 200, position: 2),
            ],
            gameUserRows: [
                $this->gameUserRow(),
                $this->gameUserRow(userId: 200),
            ],
            userRows: [
                $this->userRow(),
                $this->userRow(userId: 200, firstName: 'Bob'),
            ],
        );

        $users = $game->getUsers();

        $this->assertCount(2, $users);
        $this->assertSame('1', $users[0]->getNumber());
        $this->assertSame('Alice', $users[0]->getName());
        $this->assertSame('2', $users[1]->getNumber());
        $this->assertSame('Bob', $users[1]->getName());
    }

    // --- Multiple slots per user ---

    public function testUserWithMultipleSlotsCreatesSeparateUsers(): void
    {
        $game = $this->buildGame(
            slotRows: [
                $this->slotRow(),
                $this->slotRow(position: 3),
            ],
            gameUserRows: [
                $this->gameUserRow(),
            ],
            userRows: [
                $this->userRow(),
            ],
        );

        $users = $game->getUsers();

        $this->assertCount(2, $users);
        $this->assertSame('1', $users[0]->getNumber());
        $this->assertSame('3', $users[1]->getNumber());
        $this->assertSame('Alice', $users[0]->getName());
        $this->assertSame('Alice', $users[1]->getName());
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

    private function slotRow(int $userId = 100, int $position = 1): array
    {
        return [
            'game_id' => 1,
            'telegram_user_id' => $userId,
            'position' => $position,
        ];
    }

    private function gameUserRow(
        int $userId = 100,
        int $volleyball = 0,
        int $net = 0,
        string $time = '18:00',
    ): array {
        return [
            'game_id' => 1,
            'telegram_user_id' => $userId,
            'volleyball' => $volleyball,
            'net' => $net,
            'time' => $time,
        ];
    }

    private function userRow(
        int $userId = 100,
        string $firstName = 'Alice',
        ?string $lastName = null,
        ?string $username = null,
    ): array {
        return [
            'telegram_user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
        ];
    }

    private function buildGame(
        ?GameRecord $game = null,
        array $messageTargets = [new InlineGameMessageTarget('msg_1')],
        array $slotRows = [],
        array $gameUserRows = [],
        array $userRows = [],
    ): GameInterface {
        return new GameBuilder(
            game: $game ?? $this->gameRecord(),
            messageTargets: $messageTargets,
            slotRows: $slotRows,
            gameUserRows: $gameUserRows,
            userRows: $userRows,
            addOns: [],
        )->build();
    }
}
