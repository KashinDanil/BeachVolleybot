<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\Roster;

use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Game\Roster\Position;
use BeachVolleybot\Game\Roster\PositionInterface;
use BeachVolleybot\Game\Roster\PositionRange;
use BeachVolleybot\Game\Roster\PlayerLimit;
use PHPUnit\Framework\TestCase;

final class PlayerLimitTest extends TestCase
{
    // --- resolveLimit(): null cases ---

    public function testNullWhenPlayersPerNetUnset(): void
    {
        $limit = PlayerLimit::resolveLimit([$this->user(net: 1)], new GameSettings());

        $this->assertNull($limit->threshold);
    }

    public function testNullWhenNoNets(): void
    {
        $limit = PlayerLimit::resolveLimit([$this->user(net: 0)], new GameSettings(playersPerNet: 6));

        $this->assertNull($limit->threshold);
    }

    public function testNullForEmptyRoster(): void
    {
        $limit = PlayerLimit::resolveLimit([], new GameSettings(playersPerNet: 6));

        $this->assertNull($limit->threshold);
    }

    // --- resolveLimit(): counting nets ---

    public function testOneNet(): void
    {
        $limit = PlayerLimit::resolveLimit([$this->user(net: 1)], new GameSettings(playersPerNet: 6));

        $this->assertSame(6, $limit->threshold);
    }

    public function testTwoNetsFromTwoUsers(): void
    {
        $users = [
            $this->user(telegramUserId: 1, net: 1),
            $this->user(telegramUserId: 2, net: 1),
        ];

        $limit = PlayerLimit::resolveLimit($users, new GameSettings(playersPerNet: 6));

        $this->assertSame(12, $limit->threshold);
    }

    public function testTwoNetsFromOneUserAreSummedNotCollapsed(): void
    {
        $limit = PlayerLimit::resolveLimit([$this->user(net: 2)], new GameSettings(playersPerNet: 6));

        $this->assertSame(12, $limit->threshold);
    }

    public function testUserHoldingMultipleSlotsCountsNetOnce(): void
    {
        $users = [
            $this->user(telegramUserId: 1, position: new Position(1), net: 1),
            $this->user(telegramUserId: 1, position: new Position(2), net: 1),
            $this->user(telegramUserId: 1, position: new Position(3), net: 1),
        ];

        $limit = PlayerLimit::resolveLimit($users, new GameSettings(playersPerNet: 6));

        $this->assertSame(6, $limit->threshold);
    }

    public function testStoredZeroFallsBackToMinimum(): void
    {
        $limit = PlayerLimit::resolveLimit([$this->user(net: 1)], new GameSettings(playersPerNet: 0));

        $this->assertSame(4, $limit->threshold);
    }

    // --- isReserve() ---

    public function testIsReserveFalseWhenLimitNull(): void
    {
        $this->assertFalse(new PlayerLimit(null)->isReserve($this->user(position: new Position(99))));
    }

    public function testIsReserveFalseWithinLimit(): void
    {
        $this->assertFalse(new PlayerLimit(4)->isReserve($this->user(position: new Position(4))));
    }

    public function testIsReserveTrueBeyondLimit(): void
    {
        $this->assertTrue(new PlayerLimit(4)->isReserve($this->user(position: new Position(5))));
    }

    public function testIsReserveReadsFirstNumberOfARange(): void
    {
        $this->assertFalse(new PlayerLimit(6)->isReserve($this->user(position: new PositionRange(5, 7))));
        $this->assertTrue(new PlayerLimit(4)->isReserve($this->user(position: new PositionRange(5, 7))));
    }

    // --- Helpers ---

    private function user(
        int $telegramUserId = 1,
        PositionInterface $position = new Position(1),
        int $net = 0,
    ): User {
        return new User(
            telegramUserId: $telegramUserId,
            position: $position,
            name: 'Alice',
            link: null,
            volleyball: 0,
            net: $net,
            time: '18:00',
        );
    }
}
