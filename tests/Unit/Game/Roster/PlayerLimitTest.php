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
        $limit = PlayerLimit::resolveLimit([$this->user(net: 0, volleyball: 1)], new GameSettings(playersPerNet: 6));

        $this->assertNull($limit->threshold);
    }

    public function testNullWhenNoVolleyballs(): void
    {
        $limit = PlayerLimit::resolveLimit([$this->user(net: 1, volleyball: 0)], new GameSettings(playersPerNet: 6));

        $this->assertNull($limit->threshold);
    }

    public function testNullForEmptyRoster(): void
    {
        $limit = PlayerLimit::resolveLimit([], new GameSettings(playersPerNet: 6));

        $this->assertNull($limit->threshold);
    }

    // --- resolveLimit(): counting equipment ---

    public function testOneCourt(): void
    {
        $limit = PlayerLimit::resolveLimit([$this->user(net: 1, volleyball: 1)], new GameSettings(playersPerNet: 6));

        $this->assertSame(6, $limit->threshold);
    }

    public function testTwoCourtsFromTwoUsers(): void
    {
        $users = [
            $this->user(telegramUserId: 1, net: 1, volleyball: 1),
            $this->user(telegramUserId: 2, net: 1, volleyball: 1),
        ];

        $limit = PlayerLimit::resolveLimit($users, new GameSettings(playersPerNet: 6));

        $this->assertSame(12, $limit->threshold);
    }

    public function testTwoNetsFromOneUserAreSummedNotCollapsed(): void
    {
        $limit = PlayerLimit::resolveLimit([$this->user(net: 2, volleyball: 2)], new GameSettings(playersPerNet: 6));

        $this->assertSame(12, $limit->threshold);
    }

    public function testUserHoldingMultipleSlotsCountsEquipmentOnce(): void
    {
        $users = [
            $this->user(telegramUserId: 1, position: new Position(1), net: 1, volleyball: 1),
            $this->user(telegramUserId: 1, position: new Position(2), net: 1, volleyball: 1),
            $this->user(telegramUserId: 1, position: new Position(3), net: 1, volleyball: 1),
        ];

        $limit = PlayerLimit::resolveLimit($users, new GameSettings(playersPerNet: 6));

        $this->assertSame(6, $limit->threshold);
    }

    public function testStoredZeroFallsBackToMinimum(): void
    {
        $limit = PlayerLimit::resolveLimit([$this->user(net: 1, volleyball: 1)], new GameSettings(playersPerNet: 0));

        $this->assertSame(4, $limit->threshold);
    }

    // --- resolveLimit(): the scarcer equipment caps the courts ---

    public function testFewerVolleyballsThanNetsCapTheCourts(): void
    {
        $users = [
            $this->user(telegramUserId: 1, net: 1, volleyball: 1),
            $this->user(telegramUserId: 2, net: 1, volleyball: 0),
        ];

        $limit = PlayerLimit::resolveLimit($users, new GameSettings(playersPerNet: 6));

        $this->assertSame(6, $limit->threshold);
    }

    public function testFewerNetsThanVolleyballsCapTheCourts(): void
    {
        $users = [
            $this->user(telegramUserId: 1, net: 1, volleyball: 1),
            $this->user(telegramUserId: 2, net: 0, volleyball: 1),
        ];

        $limit = PlayerLimit::resolveLimit($users, new GameSettings(playersPerNet: 6));

        $this->assertSame(6, $limit->threshold);
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
        int $volleyball = 0,
    ): User {
        return new User(
            telegramUserId: $telegramUserId,
            position: $position,
            name: 'Alice',
            link: null,
            volleyball: $volleyball,
            net: $net,
            time: '18:00',
        );
    }
}
