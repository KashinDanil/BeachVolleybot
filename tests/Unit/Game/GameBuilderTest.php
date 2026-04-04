<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\GameBuilder;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Outgoing\TelegramMessage;
use BeachVolleybot\Tests\Unit\Game\AddOns\Stub\TitlePrefixAddOn;
use PHPUnit\Framework\TestCase;

final class GameBuilderTest extends TestCase
{
    // --- Game-level mapping ---

    public function testGameId(): void
    {
        $game = $this->buildGame(gameRow: $this->gameRow(gameId: 42));

        $this->assertSame(42, $game->getGameId());
    }

    public function testInlineMessageId(): void
    {
        $game = $this->buildGame(gameRow: $this->gameRow(inlineMessageId: 'msg_abc'));

        $this->assertSame('msg_abc', $game->getInlineMessageId());
    }

    public function testTitle(): void
    {
        $game = $this->buildGame(gameRow: $this->gameRow(title: 'Sunday Game 19:00'));

        $this->assertSame('Sunday Game 19:00', $game->getTitle());
    }

    public function testBuildTelegramMessageReturnsTelegramMessage(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gamePlayerRows: [$this->gamePlayerRow()],
            playerRows: [$this->playerRow()],
        );

        $this->assertInstanceOf(TelegramMessage::class, $game->buildTelegramMessage());
    }

    // --- No slots ---

    public function testGameWithNoSlotsHasEmptyPlayers(): void
    {
        $game = $this->buildGame();

        $this->assertSame([], $game->getPlayers());
    }

    // --- Single player mapping ---

    public function testSinglePlayerNumber(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow(position: 3)],
            gamePlayerRows: [$this->gamePlayerRow()],
            playerRows: [$this->playerRow()],
        );

        $this->assertSame('3', $game->getPlayers()[0]->getNumber());
    }

    public function testSinglePlayerVolleyballAndNet(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gamePlayerRows: [$this->gamePlayerRow(volleyball: 5, net: 2)],
            playerRows: [$this->playerRow()],
        );

        $player = $game->getPlayers()[0];

        $this->assertSame(5, $player->getVolleyball());
        $this->assertSame(2, $player->getNet());
    }

    public function testSinglePlayerTime(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gamePlayerRows: [$this->gamePlayerRow(time: '19:30')],
            playerRows: [$this->playerRow()],
        );

        $this->assertSame('19:30', $game->getPlayers()[0]->getTime());
    }

    public function testPlayerTimeNullWhenNotSet(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gamePlayerRows: [$this->gamePlayerRow()],
            playerRows: [$this->playerRow()],
        );

        $this->assertNull($game->getPlayers()[0]->getTime());
    }

    // --- Name composition ---

    public function testNameWithFirstAndLastName(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gamePlayerRows: [$this->gamePlayerRow()],
            playerRows: [$this->playerRow(lastName: 'Smith')],
        );

        $this->assertSame('Alice Smith', $game->getPlayers()[0]->getName());
    }

    public function testNameWithFirstNameOnly(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gamePlayerRows: [$this->gamePlayerRow()],
            playerRows: [$this->playerRow()],
        );

        $this->assertSame('Alice', $game->getPlayers()[0]->getName());
    }

    // --- Link ---

    public function testLinkBuiltFromUsername(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gamePlayerRows: [$this->gamePlayerRow()],
            playerRows: [$this->playerRow(username: 'alice')],
        );

        $this->assertSame('https://t.me/alice', $game->getPlayers()[0]->getLink());
    }

    public function testLinkNullWhenUsernameNull(): void
    {
        $game = $this->buildGame(
            slotRows: [$this->slotRow()],
            gamePlayerRows: [$this->gamePlayerRow()],
            playerRows: [$this->playerRow()],
        );

        $this->assertNull($game->getPlayers()[0]->getLink());
    }

    // --- Multiple players ---

    public function testMultiplePlayersOrderedBySlotPosition(): void
    {
        $game = $this->buildGame(
            slotRows: [
                $this->slotRow(),
                $this->slotRow(userId: 200, position: 2),
            ],
            gamePlayerRows: [
                $this->gamePlayerRow(),
                $this->gamePlayerRow(userId: 200),
            ],
            playerRows: [
                $this->playerRow(),
                $this->playerRow(userId: 200, firstName: 'Bob'),
            ],
        );

        $players = $game->getPlayers();

        $this->assertCount(2, $players);
        $this->assertSame('1', $players[0]->getNumber());
        $this->assertSame('Alice', $players[0]->getName());
        $this->assertSame('2', $players[1]->getNumber());
        $this->assertSame('Bob', $players[1]->getName());
    }

    // --- Multiple slots per player ---

    public function testPlayerWithMultipleSlotsCreatesSeparatePlayers(): void
    {
        $game = $this->buildGame(
            slotRows: [
                $this->slotRow(),
                $this->slotRow(position: 3),
            ],
            gamePlayerRows: [
                $this->gamePlayerRow(),
            ],
            playerRows: [
                $this->playerRow(),
            ],
        );

        $players = $game->getPlayers();

        $this->assertCount(2, $players);
        $this->assertSame('1', $players[0]->getNumber());
        $this->assertSame('3', $players[1]->getNumber());
        $this->assertSame('Alice', $players[0]->getName());
        $this->assertSame('Alice', $players[1]->getName());
    }

    // --- Add-ons ---

    public function testAddOnAppliedOnBuild(): void
    {
        $game = $this->buildGame(addOns: [TitlePrefixAddOn::class]);

        $this->assertSame('[Modified] Beach Game 18:00', $game->getTitle());
    }

    public function testMultipleAddOnsAppliedInOrder(): void
    {
        $game = $this->buildGame(addOns: [TitlePrefixAddOn::class, TitlePrefixAddOn::class]);

        $this->assertSame('[Modified] [Modified] Beach Game 18:00', $game->getTitle());
    }

    public function testNoAddOnsLeavesGameUnchanged(): void
    {
        $game = $this->buildGame(addOns: []);

        $this->assertSame('Beach Game 18:00', $game->getTitle());
    }

    // --- Helpers ---

    private function gameRow(
        int $gameId = 1,
        string $inlineQueryId = 'query_1',
        string $inlineMessageId = 'msg_1',
        string $title = 'Beach Game 18:00',
    ): array {
        return [
            'game_id' => $gameId,
            'inline_query_id' => $inlineQueryId,
            'inline_message_id' => $inlineMessageId,
            'title' => $title,
        ];
    }

    private function slotRow(int $userId = 100, int $position = 1): array
    {
        return [
            'game_id' => 1,
            'telegram_user_id' => $userId,
            'position' => $position,
        ];
    }

    private function gamePlayerRow(
        int $userId = 100,
        int $volleyball = 0,
        int $net = 0,
        ?string $time = null,
    ): array {
        return [
            'game_id' => 1,
            'telegram_user_id' => $userId,
            'volleyball' => $volleyball,
            'net' => $net,
            'time' => $time,
        ];
    }

    private function playerRow(
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
        ?array $gameRow = null,
        array $slotRows = [],
        array $gamePlayerRows = [],
        array $playerRows = [],
        array $addOns = [],
    ): GameInterface {
        return new GameBuilder(
            gameRow: $gameRow ?? $this->gameRow(),
            slotRows: $slotRows,
            gamePlayerRows: $gamePlayerRows,
            playerRows: $playerRows,
            addOns: $addOns,
        )->build();
    }
}