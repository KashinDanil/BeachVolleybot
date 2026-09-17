<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\Roster;

use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Game\Roster\Lineup;
use BeachVolleybot\Game\Roster\Position;
use BeachVolleybot\Game\Roster\PositionInterface;
use BeachVolleybot\Game\Roster\PositionRange;
use BeachVolleybot\Game\Roster\PlayerLimit;
use PHPUnit\Framework\TestCase;

final class LineupTest extends TestCase
{
    // --- No limit ---

    public function testUntouchedWhenLimitIsNull(): void
    {
        $users = [$this->user(1, new Position(1)), $this->user(2, new Position(2), net: 1)];

        $this->assertSame($users, new Lineup($users, new PlayerLimit(null))->getRowsToRender());
    }

    public function testZeroSignUps(): void
    {
        $this->assertSame([], new Lineup([], new PlayerLimit(4))->getRowsToRender());
    }

    // --- No promotion needed ---

    public function testNumbersUnchangedWhenNothingIsPromoted(): void
    {
        $users = [$this->user(1, new Position(1)), $this->user(2, new Position(2))];

        $this->assertSame(['1', '2'], $this->numbers(new Lineup($users, new PlayerLimit(4))->getRowsToRender()));
    }

    public function testBringerAlreadyInsideTheLimitIsNotMoved(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Bob'),
            $this->user(2, new Position(2), name: 'Carol'),
            $this->user(3, new Position(3), name: 'Dave'),
            $this->user(4, new PositionRange(4, 7), name: 'Alice', net: 1),
        ];

        $arranged = new Lineup($users, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Bob', 'Carol', 'Dave', 'Alice', 'Alice'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5-7'], $this->numbers($arranged));
    }

    /** A bringer whose place equals the limit exactly is inside it, so nobody moves. */
    public function testBringerExactlyAtTheLimitIsNotMoved(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Alice'),
            $this->user(2, new Position(2), name: 'Bob'),
            $this->user(3, new Position(3), name: 'Carol'),
            $this->user(4, new Position(4), name: 'Dave', net: 1),
            $this->user(5, new Position(5), name: 'Erin'),
        ];

        $arranged = new Lineup($users, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Alice', 'Bob', 'Carol', 'Dave', 'Erin'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5'], $this->numbers($arranged));
    }

    // --- Splitting the row the divider falls inside ---

    public function testRowStraddlingTheLimitIsSplit(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Alice', net: 1),
            $this->user(2, new PositionRange(2, 6), name: 'Bob'),
        ];

        $arranged = new Lineup($users, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Alice', 'Bob', 'Bob'], $this->names($arranged));
        $this->assertSame(['1', '2-4', '5-6'], $this->numbers($arranged));
    }

    public function testRowWhollyBeyondTheLimitIsNotSplit(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Alice', net: 1),
            $this->user(2, new PositionRange(2, 4), name: 'Bob'),
            $this->user(3, new PositionRange(5, 7), name: 'Carol'),
        ];

        $arranged = new Lineup($users, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['1', '2-4', '5-7'], $this->numbers($arranged));
    }

    /**
     * The reason the split cannot happen before promotion: promoting Grace shifts Alice's
     * whole block down one, so the cut lands at a different place than it would have before.
     */
    public function testSplitFollowsTheNumbersPromotionProduced(): void
    {
        $users = [
            $this->user(1, new PositionRange(1, 6), name: 'Alice'),
            $this->user(2, new Position(7), name: 'Grace', net: 1),
        ];

        $arranged = new Lineup($users, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Grace', 'Alice', 'Alice'], $this->names($arranged));
        $this->assertSame(['1', '2-4', '5-7'], $this->numbers($arranged));
    }

    // --- Promotion ---

    public function testLastPlaceBringerMovedToTopAndEveryoneRenumbered(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Alice'),
            $this->user(2, new Position(2), name: 'Bob'),
            $this->user(3, new Position(3), name: 'Carol'),
            $this->user(4, new Position(4), name: 'Dave'),
            $this->user(5, new Position(5), name: 'Erin'),
            $this->user(6, new Position(6), name: 'Frank'),
            $this->user(7, new Position(7), name: 'Grace', net: 1),
        ];

        $arranged = new Lineup($users, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Grace', 'Alice', 'Bob', 'Carol', 'Dave', 'Erin', 'Frank'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7'], $this->numbers($arranged));
    }

    public function testOnlyTheBringersFirstPlaceMoves(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Alice'),
            $this->user(2, new Position(2), name: 'Bob'),
            $this->user(3, new Position(3), name: 'Carol'),
            $this->user(4, new Position(4), name: 'Dave'),
            $this->user(5, new PositionRange(5, 6), name: 'Zoe', net: 1),
        ];

        $arranged = new Lineup($users, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Zoe', 'Alice', 'Bob', 'Carol', 'Dave', 'Zoe'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6'], $this->numbers($arranged));
    }

    /** Bob is past the limit, so Alice moves up with him even though slot 8 was inside it. */
    public function testOneBringerPushedOutMovesEveryBringerUpInSignUpOrder(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'P1'),
            $this->user(2, new Position(2), name: 'P2'),
            $this->user(3, new Position(3), name: 'P3'),
            $this->user(4, new Position(4), name: 'P4'),
            $this->user(5, new Position(5), name: 'P5'),
            $this->user(6, new Position(6), name: 'P6'),
            $this->user(7, new Position(7), name: 'P7'),
            $this->user(8, new Position(8), name: 'Alice', net: 1),
            $this->user(9, new Position(9), name: 'Bob', net: 1),
        ];

        $arranged = new Lineup($users, new PlayerLimit(8))->getRowsToRender();

        $this->assertSame(
            ['Alice', 'Bob', 'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7'],
            $this->names($arranged),
        );
    }

    public function testEveryoneBroughtANet(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Alice', net: 1),
            $this->user(2, new Position(2), name: 'Bob', net: 1),
            $this->user(3, new Position(3), name: 'Carol', net: 1),
        ];

        $arranged = new Lineup($users, new PlayerLimit(2))->getRowsToRender();

        $this->assertSame(['Alice', 'Bob', 'Carol'], $this->names($arranged));
        $this->assertSame(['1', '2', '3'], $this->numbers($arranged));
    }

    // --- Merging back into rows ---

    /** Promoting Carol leaves Bob's two rows next to each other, and the card shows them as one. */
    public function testPlacesPromotionPutsNextToEachOtherAreMerged(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Dave'),
            $this->user(2, new Position(2), name: 'Erin'),
            $this->user(3, new Position(3), name: 'Frank'),
            $this->user(4, new Position(4), name: 'Bob'),
            $this->user(5, new Position(5), name: 'Carol', net: 1),
            $this->user(4, new Position(6), name: 'Bob'),
        ];

        $arranged = new Lineup($users, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Carol', 'Dave', 'Erin', 'Frank', 'Bob'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5-6'], $this->numbers($arranged));
    }

    /** Without the add-on nothing merges, but the divider still falls in the same place. */
    public function testPlacesStayOnTheirOwnRowsWhenTheMergeAddOnIsOff(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Dave'),
            $this->user(2, new Position(2), name: 'Erin'),
            $this->user(3, new Position(3), name: 'Frank'),
            $this->user(4, new Position(4), name: 'Bob'),
            $this->user(5, new Position(5), name: 'Carol', net: 1),
            $this->user(4, new Position(6), name: 'Bob'),
        ];

        $arranged = new Lineup($users, new PlayerLimit(4), addOns: [])->getRowsToRender();

        $this->assertSame(['Carol', 'Dave', 'Erin', 'Frank', 'Bob', 'Bob'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6'], $this->numbers($arranged));
    }

    // --- Field fidelity ---

    public function testRenumberingPreservesIdNetAndVolleyball(): void
    {
        $users = [
            $this->user(1, new Position(1), name: 'Alice'),
            $this->user(2, new Position(2), name: 'Bob', volleyball: 3, net: 2, link: 'https://t.me/bob', time: '19:00'),
        ];

        $promoted = new Lineup($users, new PlayerLimit(1))->getRowsToRender()[0];

        $this->assertSame(2, $promoted->getTelegramUserId());
        $this->assertSame('Bob', $promoted->getName());
        $this->assertSame('https://t.me/bob', $promoted->getLink());
        $this->assertSame(3, $promoted->getVolleyball());
        $this->assertSame(2, $promoted->getNet());
        $this->assertSame('19:00', $promoted->getTime());
        $this->assertSame('1', $promoted->getPosition()->format());
    }

    // --- Helpers ---

    /**
     * @param UserInterface[] $users
     *
     * @return list<string>
     */
    private function names(array $users): array
    {
        return array_map(static fn(UserInterface $user): string => $user->getName(), $users);
    }

    /**
     * @param UserInterface[] $users
     *
     * @return list<string>
     */
    private function numbers(array $users): array
    {
        return array_map(static fn(UserInterface $user): string => $user->getPosition()->format(), $users);
    }

    private function user(
        int $telegramUserId,
        PositionInterface $position,
        string $name = 'Player',
        ?string $link = null,
        int $volleyball = 0,
        int $net = 0,
        string $time = '18:00',
    ): User {
        return new User(
            telegramUserId: $telegramUserId,
            position: $position,
            name: $name,
            link: $link,
            volleyball: $volleyball,
            net: $net,
            time: $time,
        );
    }
}
