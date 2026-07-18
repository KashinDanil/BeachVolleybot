<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\GameWarningCollector;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoEquipmentWarning;
use PHPUnit\Framework\TestCase;

final class GameWarningCollectorTest extends TestCase
{
    private GameWarningCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new GameWarningCollector(
            new NoEquipmentWarning(),
        );
    }

    public function testReturnsEmptyArrayWhenUsersHaveEquipment(): void
    {
        $users = [
            $this->user(volleyball: 1, net: 1),
        ];

        $this->assertSame([], $this->collector->collect($users));
    }

    public function testReturnsWarningWhenNetMissing(): void
    {
        $users = [
            $this->user(volleyball: 1, net: 0),
        ];

        $this->assertSame(['Someone needs to bring a net'], $this->collector->collect($users));
    }

    public function testReturnsWarningWhenVolleyballMissing(): void
    {
        $users = [
            $this->user(volleyball: 0, net: 1),
        ];

        $this->assertSame(['Someone needs to bring a volleyball'], $this->collector->collect($users));
    }

    public function testReturnsCombinedWarningWhenBothMissing(): void
    {
        $users = [
            $this->user(volleyball: 0, net: 0),
        ];

        $this->assertSame(
            ['Someone needs to bring a net and a volleyball'],
            $this->collector->collect($users),
        );
    }

    public function testReturnsEmptyArrayWhenNoWarnings(): void
    {
        $collector = new GameWarningCollector();
        $users = [
            $this->user(volleyball: 0, net: 0),
        ];

        $this->assertSame([], $collector->collect($users));
    }

    private function user(int $volleyball, int $net): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getVolleyball')->willReturn($volleyball);
        $user->method('getNet')->willReturn($net);

        return $user;
    }
}
