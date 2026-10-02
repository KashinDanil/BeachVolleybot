<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\Roster;

use BeachVolleybot\Game\GameSlotRecord;
use BeachVolleybot\Game\GameUserRecord;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Game\Roster\RosterBuilder;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RosterBuilderTest extends TestCase
{
    use CreatesUserRecords;

    public function testOneUserPerSlotInSlotOrderWithTheirEquipment(): void
    {
        $users = new RosterBuilder(
            [$this->gameSlotRecord(200, 1), $this->gameSlotRecord(201, 2), $this->gameSlotRecord(200, 3)],
            [$this->gameUserRecord(200, volleyball: 1, net: 1), $this->gameUserRecord(201)],
        )->build();

        $this->assertSame([200, 201, 200], array_map(static fn(User $user): int => $user->getTelegramUserId(), $users));
        $this->assertSame(['1', '2', '3'], array_map(static fn(User $user): string => $user->getPosition()->format(), $users));
        $this->assertSame([1, 0, 1], array_map(static fn(User $user): int => $user->getNet(), $users));
    }

    public function testNamesAndLinksComeFromTheUserRecords(): void
    {
        $user = new RosterBuilder(
            [$this->gameSlotRecord(200, 1)],
            [$this->gameUserRecord(200)],
            [$this->userRecord(200, firstName: 'Alice', lastName: 'Smith', username: 'alice')],
        )->build()[0];

        $this->assertSame('Alice Smith', $user->getName());
        $this->assertSame('https://t.me/alice', $user->getLink());
    }

    public function testNamesAndLinksStayBlankWithoutUserRecords(): void
    {
        $user = new RosterBuilder([$this->gameSlotRecord(200, 1)], [$this->gameUserRecord(200)])->build()[0];

        $this->assertSame('', $user->getName());
        $this->assertNull($user->getLink());
    }

    private function gameSlotRecord(int $telegramUserId, int $position): GameSlotRecord
    {
        return new GameSlotRecord(
            gameId: 1,
            telegramUserId: $telegramUserId,
            position: $position,
            createdAt: new DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }

    private function gameUserRecord(int $telegramUserId, int $volleyball = 0, int $net = 0): GameUserRecord
    {
        return new GameUserRecord(
            gameId: 1,
            telegramUserId: $telegramUserId,
            time: '18:00',
            volleyball: $volleyball,
            net: $net,
            createdAt: new DateTimeImmutable('2026-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }
}
