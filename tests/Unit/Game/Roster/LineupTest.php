<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\Roster;

use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Game\Models\PlayerInterface;
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
        $players = [$this->player(1, new Position(1)), $this->player(2, new Position(2), net: 1)];

        $this->assertSame($players, new Lineup($players, new PlayerLimit(null))->getRowsToRender());
    }

    public function testZeroSignUps(): void
    {
        $this->assertSame([], new Lineup([], new PlayerLimit(4))->getRowsToRender());
    }

    // --- No promotion needed ---

    public function testNumbersUnchangedWhenNothingIsPromoted(): void
    {
        $players = [$this->player(1, new Position(1)), $this->player(2, new Position(2))];

        $this->assertSame(['1', '2'], $this->numbers(new Lineup($players, new PlayerLimit(4))->getRowsToRender()));
    }

    public function testBringerAlreadyInsideTheLimitIsNotMoved(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Bob'),
            $this->player(2, new Position(2), name: 'Carol'),
            $this->player(3, new Position(3), name: 'Dave'),
            $this->player(4, new PositionRange(4, 7), name: 'Alice', net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Bob', 'Carol', 'Dave', 'Alice', 'Alice'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5-7'], $this->numbers($arranged));
    }

    /** A bringer whose place equals the limit exactly is inside it, so nobody moves. */
    public function testBringerExactlyAtTheLimitIsNotMoved(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave', net: 1),
            $this->player(5, new Position(5), name: 'Erin'),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Alice', 'Bob', 'Carol', 'Dave', 'Erin'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5'], $this->numbers($arranged));
    }

    // --- Splitting the row the divider falls inside ---

    public function testRowStraddlingTheLimitIsSplit(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice', net: 1),
            $this->player(2, new PositionRange(2, 6), name: 'Bob'),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Alice', 'Bob', 'Bob'], $this->names($arranged));
        $this->assertSame(['1', '2-4', '5-6'], $this->numbers($arranged));
    }

    public function testRowWhollyBeyondTheLimitIsNotSplit(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice', net: 1),
            $this->player(2, new PositionRange(2, 4), name: 'Bob'),
            $this->player(3, new PositionRange(5, 7), name: 'Carol'),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['1', '2-4', '5-7'], $this->numbers($arranged));
    }

    /**
     * The reason the split cannot happen before promotion: promoting Grace shifts Alice's
     * whole block down one, so the cut lands at a different place than it would have before.
     */
    public function testSplitFollowsTheNumbersPromotionProduced(): void
    {
        $players = [
            $this->player(1, new PositionRange(1, 6), name: 'Alice'),
            $this->player(2, new Position(7), name: 'Grace', volleyball: 1, net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Grace', 'Alice', 'Alice'], $this->names($arranged));
        $this->assertSame(['1', '2-4', '5-7'], $this->numbers($arranged));
    }

    // --- Promotion ---

    public function testLastPlaceBringerMovedToTopAndEveryoneRenumbered(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Erin'),
            $this->player(6, new Position(6), name: 'Frank'),
            $this->player(7, new Position(7), name: 'Grace', volleyball: 1, net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Grace', 'Alice', 'Bob', 'Carol', 'Dave', 'Erin', 'Frank'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7'], $this->numbers($arranged));
    }

    public function testOnlyTheBringersFirstPlaceMoves(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new PositionRange(5, 6), name: 'Zoe', volleyball: 1, net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Zoe', 'Alice', 'Bob', 'Carol', 'Dave', 'Zoe'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6'], $this->numbers($arranged));
    }

    /** Bob is past the limit, so Alice moves up with him even though slot 8 was inside it. */
    public function testOneBringerPushedOutMovesEveryBringerUpInSignUpOrder(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'P1'),
            $this->player(2, new Position(2), name: 'P2'),
            $this->player(3, new Position(3), name: 'P3'),
            $this->player(4, new Position(4), name: 'P4'),
            $this->player(5, new Position(5), name: 'P5'),
            $this->player(6, new Position(6), name: 'P6'),
            $this->player(7, new Position(7), name: 'P7'),
            $this->player(8, new Position(8), name: 'Alice', volleyball: 1, net: 1),
            $this->player(9, new Position(9), name: 'Bob', volleyball: 1, net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(8))->getRowsToRender();

        $this->assertSame(
            ['Alice', 'Bob', 'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7'],
            $this->names($arranged),
        );
    }

    public function testEveryoneBroughtANet(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice', net: 1),
            $this->player(2, new Position(2), name: 'Bob', net: 1),
            $this->player(3, new Position(3), name: 'Carol', net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(2))->getRowsToRender();

        $this->assertSame(['Alice', 'Bob', 'Carol'], $this->names($arranged));
        $this->assertSame(['1', '2', '3'], $this->numbers($arranged));
    }

    // --- A ball behind every net ---

    /** A net with no ball to match it equips no court, so its bringer is never pulled up. */
    public function testANetWithNoBallInTheGameIsNotPromoted(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Xena', net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Alice', 'Bob', 'Carol', 'Dave', 'Xena'], $this->names($arranged));
    }

    public function testNobodyMovesWhenTheCourtAlreadyHasABallForEveryNet(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Xena', net: 1),
            $this->player(2, new Position(2), name: 'Alice', volleyball: 1),
            $this->player(3, new Position(3), name: 'Bob'),
            $this->player(4, new Position(4), name: 'Carol'),
            $this->player(5, new Position(5), name: 'Ivan', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Alice', 'Bob', 'Carol', 'Ivan'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5'], $this->numbers($arranged));
    }

    public function testBallHolderIsPulledUpAlongWithTheNetBringer(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Xena', net: 1),
            $this->player(6, new Position(6), name: 'Ivan', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Ivan', 'Alice', 'Bob', 'Carol', 'Dave'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6'], $this->numbers($arranged));
    }

    /** Equipment does not reorder the front block: Ivan signed up before Xena and stays ahead. */
    public function testPromotedPlayersKeepTheirSignUpOrder(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Ivan', volleyball: 1),
            $this->player(6, new Position(6), name: 'Xena', net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Ivan', 'Xena', 'Alice', 'Bob', 'Carol', 'Dave'], $this->names($arranged));
    }

    /**
     * Two nets and three balls, one of them already on court: Alice counts towards the two and
     * moves up with Ivan, and Jane — the third ball — is left where she signed up. Xena keeps
     * the head of the list she signed up for; a ball never costs a net bringer their place.
     */
    public function testBallAlreadyOnCourtCountsAndMovesUpWithTheOneThatJoinsIt(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Xena', net: 2),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Alice', volleyball: 1),
            $this->player(4, new Position(4), name: 'Carl'),
            $this->player(5, new Position(5), name: 'Dan'),
            $this->player(6, new Position(6), name: 'Eve'),
            $this->player(7, new Position(7), name: 'Finn'),
            $this->player(8, new Position(8), name: 'Gus'),
            $this->player(9, new Position(9), name: 'Ivan', volleyball: 1),
            $this->player(10, new Position(10), name: 'Jane', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(8))->getRowsToRender();

        $this->assertSame(
            ['Xena', 'Alice', 'Ivan', 'Bob', 'Carl', 'Dan', 'Eve', 'Finn', 'Gus', 'Jane'],
            $this->names($arranged),
        );
    }

    /** Three balls below the line against two nets: only as many as the nets need come up. */
    public function testOnlyAsManyBallHoldersAsThereAreNetsAreMovedUp(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Xena', net: 2),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Ivan', volleyball: 1),
            $this->player(6, new Position(6), name: 'Jane', volleyball: 1),
            $this->player(7, new Position(7), name: 'Kate', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Ivan', 'Jane', 'Bob', 'Carol', 'Dave', 'Kate'], $this->names($arranged));
    }

    /** Two balls in one pair of hands cover two nets, so the second holder stays put. */
    public function testOneHolderCarryingTwoBallsCoversTwoNets(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Xena', net: 2),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Ivan', volleyball: 2),
            $this->player(6, new Position(6), name: 'Jane', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Ivan', 'Bob', 'Carol', 'Dave', 'Jane'], $this->names($arranged));
    }

    /** Fewer balls than nets in the whole game — everything there is comes up, and that is that. */
    public function testEveryBallComesUpWhenThereAreFewerOfThemThanNets(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Xena', net: 3),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Ivan', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Ivan', 'Bob', 'Carol', 'Dave'], $this->names($arranged));
    }

    /**
     * Two nets held by two people but only one ball make one court: only the first net and the
     * ball are essential, so those two come up in sign-up order and the second net is left below.
     */
    public function testASecondNetWithNoBallToMatchItStaysBelowTheLine(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'player1'),
            $this->player(2, new Position(2), name: 'player2', volleyball: 1),
            $this->player(3, new Position(3), name: 'player3'),
            $this->player(4, new Position(4), name: 'player4'),
            $this->player(5, new Position(5), name: 'player5', net: 1),
            $this->player(6, new Position(6), name: 'player6', net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(
            ['player2', 'player5', 'player1', 'player3', 'player4', 'player6'],
            $this->names($arranged),
        );
        $this->assertSame(['1', '2', '3', '4', '5', '6'], $this->numbers($arranged));
    }

    /** One pair of hands holding both keeps one row, not one per piece of equipment. */
    public function testCarryingBothANetAndABallPromotesThePlayerOnce(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'P1'),
            $this->player(2, new Position(2), name: 'P2'),
            $this->player(3, new Position(3), name: 'P3'),
            $this->player(4, new Position(4), name: 'P4'),
            $this->player(5, new Position(5), name: 'P5'),
            $this->player(6, new Position(6), name: 'P6'),
            $this->player(7, new Position(7), name: 'P7'),
            $this->player(8, new Position(8), name: 'P8'),
            $this->player(9, new Position(9), name: 'Xena', volleyball: 1, net: 2),
            $this->player(10, new Position(10), name: 'Ivan', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(8))->getRowsToRender();

        $this->assertSame(
            ['Xena', 'Ivan', 'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8'],
            $this->names($arranged),
        );
    }

    /**
     * Xena sits exactly on the limit, so the nets alone would leave her alone — but pulling Ivan
     * up shifts her out, and a net bringer below the line is the one thing that must never happen.
     */
    public function testPullingABallUpNeverPushesANetBringerOut(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Xena', net: 1),
            $this->player(5, new Position(5), name: 'Ivan', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Ivan', 'Alice', 'Bob', 'Carol'], $this->names($arranged));
    }

    /** A ball holder with several places brings only the first one up, like a net bringer does. */
    public function testOnlyTheBallHoldersFirstPlaceMoves(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Xena', net: 1),
            $this->player(6, new PositionRange(6, 8), name: 'Zoe', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Zoe', 'Alice', 'Bob', 'Carol', 'Dave', 'Zoe'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7-8'], $this->numbers($arranged));
    }

    /**
     * Promotion only decides which of two groups somebody lands in. Inside each group sign-up
     * order survives untouched — Alice before Xena before Ivan at the front, Bob before Carol
     * before Dave before Erin before Frank behind them, and Bob's three places still together.
     */
    public function testBothTheCarriersAndEveryoneBehindThemKeepTheirOrder(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice', volleyball: 1),
            $this->player(2, new PositionRange(2, 4), name: 'Bob'),
            $this->player(3, new Position(5), name: 'Xena', net: 2),
            $this->player(4, new Position(6), name: 'Carol'),
            $this->player(5, new Position(7), name: 'Dave'),
            $this->player(6, new Position(8), name: 'Erin'),
            $this->player(7, new Position(9), name: 'Ivan', volleyball: 1),
            $this->player(8, new Position(10), name: 'Frank'),
        ];

        $arranged = new Lineup($players, new PlayerLimit(8))->getRowsToRender();

        $this->assertSame(['Alice', 'Xena', 'Ivan', 'Bob', 'Carol', 'Dave', 'Erin', 'Frank'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4-6', '7', '8', '9', '10'], $this->numbers($arranged));
    }

    // --- Nets and balls in the same hands, and in other hands too ---

    /** Everything the game needs is on one person, so nobody else is disturbed. */
    public function testOnePersonCarryingBothComesUpAlone(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Xena', volleyball: 1, net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Alice', 'Bob', 'Carol', 'Dave'], $this->names($arranged));
    }

    /**
     * Xena carries a ball and both nets from below the line; Alice's ball on court covers the
     * other net. The two of them come up, and the spare balls either side stay where they are.
     */
    public function testCarrierOfBothComesUpWithTheBallAlreadyOnCourt(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice', volleyball: 1),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Xena', volleyball: 1, net: 2),
            $this->player(6, new Position(6), name: 'Ivan', volleyball: 1),
            $this->player(7, new Position(7), name: 'Jane', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Alice', 'Xena', 'Bob', 'Carol', 'Dave', 'Ivan', 'Jane'], $this->names($arranged));
    }

    /** Two nets either side of the line and two balls either side: all four carriers come up. */
    public function testCarriersFromBothSidesOfTheLineComeUpTogether(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice', volleyball: 1),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Xena', net: 1),
            $this->player(4, new Position(4), name: 'Dan'),
            $this->player(5, new Position(5), name: 'Eve'),
            $this->player(6, new Position(6), name: 'Finn'),
            $this->player(7, new Position(7), name: 'Gus'),
            $this->player(8, new Position(8), name: 'Hank'),
            $this->player(9, new Position(9), name: 'Yara', net: 1),
            $this->player(10, new Position(10), name: 'Ivan', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(8))->getRowsToRender();

        $this->assertSame(
            ['Alice', 'Xena', 'Yara', 'Ivan', 'Bob', 'Dan', 'Eve', 'Finn', 'Gus', 'Hank'],
            $this->names($arranged),
        );
    }

    /** Two balls in one pair of hands below the line cover both nets, so the third ball stays. */
    public function testTwoBallsFromOneCarrierBelowTheLineCoverBothNets(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new Position(5), name: 'Xena', net: 2),
            $this->player(6, new Position(6), name: 'Ivan', volleyball: 2),
            $this->player(7, new Position(7), name: 'Jane', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Ivan', 'Alice', 'Bob', 'Carol', 'Dave', 'Jane'], $this->names($arranged));
    }

    /** Spare equipment nobody needs, above and below the line alike, moves nothing. */
    public function testSpareBallsOnEitherSideOfTheLineChangeNothing(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Xena', net: 1),
            $this->player(2, new Position(2), name: 'Alice', volleyball: 1),
            $this->player(3, new Position(3), name: 'Bob', volleyball: 1),
            $this->player(4, new Position(4), name: 'Carol'),
            $this->player(5, new Position(5), name: 'Ivan', volleyball: 1),
            $this->player(6, new Position(6), name: 'Jane', volleyball: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Alice', 'Bob', 'Carol', 'Ivan', 'Jane'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6'], $this->numbers($arranged));
    }

    /** A carrier of both holding several places still brings only the first of them up. */
    public function testCarrierOfBothWithSeveralPlacesMovesOnlyTheFirst(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob'),
            $this->player(3, new Position(3), name: 'Carol'),
            $this->player(4, new Position(4), name: 'Dave'),
            $this->player(5, new PositionRange(5, 7), name: 'Xena', volleyball: 1, net: 1),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Xena', 'Alice', 'Bob', 'Carol', 'Dave', 'Xena'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6-7'], $this->numbers($arranged));
    }

    // --- Merging back into rows ---

    /** Promoting Carol leaves Bob's two rows next to each other, and the card shows them as one. */
    public function testPlacesPromotionPutsNextToEachOtherAreMerged(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Dave'),
            $this->player(2, new Position(2), name: 'Erin'),
            $this->player(3, new Position(3), name: 'Frank'),
            $this->player(4, new Position(4), name: 'Bob'),
            $this->player(5, new Position(5), name: 'Carol', volleyball: 1, net: 1),
            $this->player(4, new Position(6), name: 'Bob'),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4))->getRowsToRender();

        $this->assertSame(['Carol', 'Dave', 'Erin', 'Frank', 'Bob'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5-6'], $this->numbers($arranged));
    }

    /** Without the add-on nothing merges, but the divider still falls in the same place. */
    public function testPlacesStayOnTheirOwnRowsWhenTheMergeAddOnIsOff(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Dave'),
            $this->player(2, new Position(2), name: 'Erin'),
            $this->player(3, new Position(3), name: 'Frank'),
            $this->player(4, new Position(4), name: 'Bob'),
            $this->player(5, new Position(5), name: 'Carol', volleyball: 1, net: 1),
            $this->player(4, new Position(6), name: 'Bob'),
        ];

        $arranged = new Lineup($players, new PlayerLimit(4), addOns: [])->getRowsToRender();

        $this->assertSame(['Carol', 'Dave', 'Erin', 'Frank', 'Bob', 'Bob'], $this->names($arranged));
        $this->assertSame(['1', '2', '3', '4', '5', '6'], $this->numbers($arranged));
    }

    // --- Playing user ids ---

    public function testPlayingUserIdsFollowSignUpOrderInsideTheLimit(): void
    {
        $players = [
            $this->player(1, new Position(1)),
            $this->player(2, new Position(2)),
            $this->player(3, new Position(3)),
        ];

        $this->assertSame([1, 2], new Lineup($players, new PlayerLimit(2))->getPlayingUserIds());
    }

    public function testPromotedNetBringerIsPlaying(): void
    {
        $players = [
            $this->player(1, new Position(1), volleyball: 1),
            $this->player(2, new Position(2)),
            $this->player(3, new Position(3), net: 1),
        ];

        $this->assertSame([1, 3], new Lineup($players, new PlayerLimit(2))->getPlayingUserIds());
    }

    public function testUserStraddlingTheLimitIsListedOnce(): void
    {
        $players = [
            $this->player(1, new Position(1)),
            $this->player(2, new PositionRange(2, 4)),
        ];

        $this->assertSame([1, 2], new Lineup($players, new PlayerLimit(3))->getPlayingUserIds());
    }

    public function testUserFullyBelowTheLimitIsNotPlaying(): void
    {
        $players = [
            $this->player(1, new PositionRange(1, 2)),
            $this->player(2, new Position(3)),
        ];

        $this->assertSame([1], new Lineup($players, new PlayerLimit(2))->getPlayingUserIds());
    }

    public function testEveryoneIsPlayingWithoutALimit(): void
    {
        $players = [$this->player(1, new Position(1)), $this->player(2, new PositionRange(2, 3))];
        $lineup = new Lineup($players, new PlayerLimit(null));

        $this->assertFalse($lineup->hasLimit());
        $this->assertSame([1, 2], $lineup->getPlayingUserIds());
    }

    // --- forSettings ---

    public function testForSettingsWithoutPlayersPerNetHasNoLimit(): void
    {
        $players = [$this->player(1, new Position(1), volleyball: 1, net: 1), $this->player(2, new Position(2))];

        $this->assertFalse(Lineup::forSettings($players, new GameSettings())->hasLimit());
    }

    public function testForSettingsWithoutACourtHasNoLimit(): void
    {
        $players = [$this->player(1, new Position(1), net: 1), $this->player(2, new Position(2))];

        $this->assertFalse(Lineup::forSettings($players, new GameSettings(playersPerNet: 4))->hasLimit());
    }

    public function testForSettingsPutsEveryonePastPlayersPerNetTimesCourtsInTheReserve(): void
    {
        $players = [
            $this->player(1, new Position(1), volleyball: 1, net: 1),
            $this->player(2, new Position(2)),
            $this->player(3, new Position(3)),
            $this->player(4, new Position(4)),
            $this->player(5, new Position(5)),
        ];

        $lineup = Lineup::forSettings($players, new GameSettings(playersPerNet: 4), []);

        $this->assertTrue($lineup->hasLimit());
        $this->assertFalse($lineup->isReserve($players[3]));
        $this->assertTrue($lineup->isReserve($players[4]));
        $this->assertSame([1, 2, 3, 4], $lineup->getPlayingUserIds());
    }

    // --- Field fidelity ---

    public function testRenumberingPreservesIdNetAndVolleyball(): void
    {
        $players = [
            $this->player(1, new Position(1), name: 'Alice'),
            $this->player(2, new Position(2), name: 'Bob', volleyball: 3, net: 2, link: 'https://t.me/bob', time: '19:00'),
        ];

        $promoted = new Lineup($players, new PlayerLimit(1))->getRowsToRender()[0];

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
     * @param PlayerInterface[] $players
     *
     * @return list<string>
     */
    private function names(array $players): array
    {
        return array_map(static fn(PlayerInterface $player): string => $player->getName(), $players);
    }

    /**
     * @param PlayerInterface[] $players
     *
     * @return list<string>
     */
    private function numbers(array $players): array
    {
        return array_map(static fn(PlayerInterface $player): string => $player->getPosition()->format(), $players);
    }

    private function player(
        int $telegramUserId,
        PositionInterface $position,
        string $name = 'Player',
        ?string $link = null,
        int $volleyball = 0,
        int $net = 0,
        string $time = '18:00',
    ): Player {
        return new Player(
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
