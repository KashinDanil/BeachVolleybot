<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns;

use BeachVolleybot\Game\AddOns\MergeConsecutiveSlotsAddOn;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\Player;
use PHPUnit\Framework\TestCase;

final class MergeConsecutiveSlotsAddOnTest extends TestCase
{
    private MergeConsecutiveSlotsAddOn $addOn;

    protected function setUp(): void
    {
        $this->addOn = new MergeConsecutiveSlotsAddOn();
    }

    // --- No merging ---

    public function testEmptyPlayers(): void
    {
        $game = $this->game([]);

        $this->transform($game);

        $this->assertSame([], $game->players);
    }

    public function testSinglePlayerSingleSlot(): void
    {
        $game = $this->game([
            $this->player(telegramUserId: 1, number: '1'),
        ]);

        $this->transform($game);

        $this->assertCount(1, $game->players);
        $this->assertSame('1', $game->players[0]->getNumber());
    }

    public function testTwoDifferentPlayers(): void
    {
        $game = $this->game([
            $this->player(telegramUserId: 1, number: '1', name: 'Alice'),
            $this->player(telegramUserId: 2, number: '2', name: 'Bob'),
        ]);

        $this->transform($game);

        $this->assertCount(2, $game->players);
        $this->assertSame('1', $game->players[0]->getNumber());
        $this->assertSame('2', $game->players[1]->getNumber());
    }

    // --- Merging consecutive slots ---

    public function testTwoConsecutiveSlotsMerged(): void
    {
        $game = $this->game([
            $this->player(telegramUserId: 1, number: '1'),
            $this->player(telegramUserId: 1, number: '2'),
        ]);

        $this->transform($game);

        $this->assertCount(1, $game->players);
        $this->assertSame('1-2', $game->players[0]->getNumber());
    }

    public function testThreeConsecutiveSlotsMerged(): void
    {
        $game = $this->game([
            $this->player(telegramUserId: 1, number: '1'),
            $this->player(telegramUserId: 1, number: '2'),
            $this->player(telegramUserId: 1, number: '3'),
        ]);

        $this->transform($game);

        $this->assertCount(1, $game->players);
        $this->assertSame('1-3', $game->players[0]->getNumber());
    }

    public function testMergedPlayerPreservesAttributes(): void
    {
        $game = $this->game([
            $this->player(telegramUserId: 1, number: '1', name: 'Alice', link: 'https://t.me/alice', volleyball: 3, net: 2, time: '19:00'),
            $this->player(telegramUserId: 1, number: '2', name: 'Alice', link: 'https://t.me/alice', volleyball: 3, net: 2, time: '19:00'),
        ]);

        $this->transform($game);

        $player = $game->players[0];

        $this->assertSame(1, $player->getTelegramUserId());
        $this->assertSame('Alice', $player->getName());
        $this->assertSame('https://t.me/alice', $player->getLink());
        $this->assertSame(3, $player->getVolleyball());
        $this->assertSame(2, $player->getNet());
        $this->assertSame('19:00', $player->getTime());
    }

    // --- Non-consecutive same player ---

    public function testSamePlayerNonConsecutiveNotMerged(): void
    {
        $game = $this->game([
            $this->player(telegramUserId: 1, number: '1', name: 'Alice'),
            $this->player(telegramUserId: 2, number: '2', name: 'Bob'),
            $this->player(telegramUserId: 1, number: '3', name: 'Alice'),
        ]);

        $this->transform($game);

        $this->assertCount(3, $game->players);
        $this->assertSame('1', $game->players[0]->getNumber());
        $this->assertSame('2', $game->players[1]->getNumber());
        $this->assertSame('3', $game->players[2]->getNumber());
    }

    // --- Mixed scenario ---

    public function testConsecutiveThenGapThenConsecutive(): void
    {
        $game = $this->game([
            $this->player(telegramUserId: 1, number: '1', name: 'Alice'),
            $this->player(telegramUserId: 1, number: '2', name: 'Alice'),
            $this->player(telegramUserId: 2, number: '3', name: 'Bob'),
            $this->player(telegramUserId: 1, number: '4', name: 'Alice'),
            $this->player(telegramUserId: 1, number: '5', name: 'Alice'),
        ]);

        $this->transform($game);

        $this->assertCount(3, $game->players);
        $this->assertSame('1-2', $game->players[0]->getNumber());
        $this->assertSame('3', $game->players[1]->getNumber());
        $this->assertSame('4-5', $game->players[2]->getNumber());
    }

    // --- Game properties preserved ---

    public function testGamePropertiesPreserved(): void
    {
        $game = $this->game([], gameId: 42, title: 'Sunday Game 18:00');

        $this->transform($game);

        $this->assertSame(42, $game->getGameId());
        $this->assertSame('Sunday Game 18:00', $game->title);
    }

    // --- Helpers ---

    private function transform(Game $game): void
    {
        $this->addOn->transform($game);
    }

    private function game(
        array $players,
        int $gameId = 1,
        string $title = 'Beach Game 18:00',
    ): Game {
        return new Game(
            gameId: $gameId,
            inlineQueryId: 'query_1',
            inlineMessageId: 'msg_1',
            title: $title,
            players: $players,
        );
    }

    private function player(
        int $telegramUserId = 1,
        string $number = '1',
        string $name = 'Alice',
        ?string $link = null,
        int $volleyball = 0,
        int $net = 0,
        ?string $time = null,
    ): Player {
        return new Player(
            telegramUserId: $telegramUserId,
            number: $number,
            name: $name,
            link: $link,
            volleyball: $volleyball,
            net: $net,
            time: $time,
        );
    }
}
