<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns;

use BeachVolleybot\Game\AddOns\MergeConsecutiveSlotsAddOn;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Game\Roster\Position;
use BeachVolleybot\Game\Roster\PositionRange;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MergeConsecutiveSlotsAddOnTest extends TestCase
{
    private MergeConsecutiveSlotsAddOn $addOn;

    protected function setUp(): void
    {
        $this->addOn = new MergeConsecutiveSlotsAddOn();
    }

    // --- No merging ---

    public function testEmptyUsers(): void
    {
        $game = $this->game([]);

        $this->transform($game);

        $this->assertSame([], $game->users);
    }

    public function testSingleUserSingleSlot(): void
    {
        $game = $this->game([
            $this->user(telegramUserId: 1, number: 1),
        ]);

        $this->transform($game);

        $this->assertCount(1, $game->users);
        $this->assertSame('1', $game->users[0]->getPosition()->format());
    }

    public function testTwoDifferentUsers(): void
    {
        $game = $this->game([
            $this->user(telegramUserId: 1, number: 1, name: 'Alice'),
            $this->user(telegramUserId: 2, number: 2, name: 'Bob'),
        ]);

        $this->transform($game);

        $this->assertCount(2, $game->users);
        $this->assertSame('1', $game->users[0]->getPosition()->format());
        $this->assertSame('2', $game->users[1]->getPosition()->format());
    }

    // --- Merging consecutive slots ---

    public function testTwoConsecutiveSlotsMerged(): void
    {
        $game = $this->game([
            $this->user(telegramUserId: 1, number: 1),
            $this->user(telegramUserId: 1, number: 2),
        ]);

        $this->transform($game);

        $this->assertCount(1, $game->users);
        $this->assertSame('1-2', $game->users[0]->getPosition()->format());
    }

    public function testThreeConsecutiveSlotsMerged(): void
    {
        $game = $this->game([
            $this->user(telegramUserId: 1, number: 1),
            $this->user(telegramUserId: 1, number: 2),
            $this->user(telegramUserId: 1, number: 3),
        ]);

        $this->transform($game);

        $this->assertCount(1, $game->users);
        $this->assertSame('1-3', $game->users[0]->getPosition()->format());
    }

    public function testMergedUserPreservesAttributes(): void
    {
        $game = $this->game([
            $this->user(telegramUserId: 1, number: 1, name: 'Alice', link: 'https://t.me/alice', volleyball: 3, net: 2, time: '19:00'),
            $this->user(telegramUserId: 1, number: 2, name: 'Alice', link: 'https://t.me/alice', volleyball: 3, net: 2, time: '19:00'),
        ]);

        $this->transform($game);

        $user = $game->users[0];

        $this->assertSame(1, $user->getTelegramUserId());
        $this->assertSame('Alice', $user->getName());
        $this->assertSame('https://t.me/alice', $user->getLink());
        $this->assertSame(3, $user->getVolleyball());
        $this->assertSame(2, $user->getNet());
        $this->assertSame('19:00', $user->getTime());
    }

    // --- Non-consecutive same user ---

    public function testSameUserNonConsecutiveNotMerged(): void
    {
        $game = $this->game([
            $this->user(telegramUserId: 1, number: 1, name: 'Alice'),
            $this->user(telegramUserId: 2, number: 2, name: 'Bob'),
            $this->user(telegramUserId: 1, number: 3, name: 'Alice'),
        ]);

        $this->transform($game);

        $this->assertCount(3, $game->users);
        $this->assertSame('1', $game->users[0]->getPosition()->format());
        $this->assertSame('2', $game->users[1]->getPosition()->format());
        $this->assertSame('3', $game->users[2]->getPosition()->format());
    }

    // --- Mixed scenario ---

    public function testConsecutiveThenGapThenConsecutive(): void
    {
        $game = $this->game([
            $this->user(telegramUserId: 1, number: 1, name: 'Alice'),
            $this->user(telegramUserId: 1, number: 2, name: 'Alice'),
            $this->user(telegramUserId: 2, number: 3, name: 'Bob'),
            $this->user(telegramUserId: 1, number: 4, name: 'Alice'),
            $this->user(telegramUserId: 1, number: 5, name: 'Alice'),
        ]);

        $this->transform($game);

        $this->assertCount(3, $game->users);
        $this->assertSame('1-2', $game->users[0]->getPosition()->format());
        $this->assertSame('3', $game->users[1]->getPosition()->format());
        $this->assertSame('4-5', $game->users[2]->getPosition()->format());
    }

    // --- Positions an upstream add-on already merged ---

    public function testRangesFromAnEarlierAddOnAreMergedNotRejected(): void
    {
        $game = $this->game([
            $this->rangeUser(telegramUserId: 1, first: 1, last: 2),
            $this->rangeUser(telegramUserId: 1, first: 3, last: 4),
            $this->user(telegramUserId: 2, number: 5, name: 'Bob'),
        ]);

        $this->transform($game);

        $this->assertCount(2, $game->users);
        $this->assertSame('1-4', $game->users[0]->getPosition()->format());
        $this->assertSame('5', $game->users[1]->getPosition()->format());
    }

    public function testLoneRangeCoveringOneSlotCollapsesToASinglePosition(): void
    {
        $game = $this->game([
            $this->rangeUser(telegramUserId: 1, first: 3, last: 3),
        ]);

        $this->transform($game);

        $this->assertEquals(new Position(3), $game->users[0]->getPosition());
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
        $this->addOn->applyTo($game);
    }

    private function game(
        array $users,
        int $gameId = 1,
        string $title = 'Beach Game 18:00',
    ): Game {
        return new Game(
            gameId: $gameId,
            gameKey: 'query_1',
            messageTargets: [],
            title: $title,
            users: $users,
            createdAt: new DateTimeImmutable(),
            kickoffAt: new DateTimeImmutable('2099-12-31 18:00:00'),
        );
    }

    private function user(
        int $telegramUserId = 1,
        int $number = 1,
        string $name = 'Alice',
        ?string $link = null,
        int $volleyball = 0,
        int $net = 0,
        string $time = '18:00',
    ): User {
        return new User(
            telegramUserId: $telegramUserId,
            position: new Position($number),
            name: $name,
            link: $link,
            volleyball: $volleyball,
            net: $net,
            time: $time,
        );
    }

    private function rangeUser(int $telegramUserId, int $first, int $last, string $name = 'Alice'): User
    {
        return new User(
            telegramUserId: $telegramUserId,
            position: new PositionRange($first, $last),
            name: $name,
            link: null,
            volleyball: 0,
            net: 0,
            time: '18:00',
        );
    }
}
