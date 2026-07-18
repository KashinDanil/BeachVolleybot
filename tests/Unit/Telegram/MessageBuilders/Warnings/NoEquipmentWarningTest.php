<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoEquipmentWarning;
use PHPUnit\Framework\TestCase;

final class NoEquipmentWarningTest extends TestCase
{
    private NoEquipmentWarning $warning;

    protected function setUp(): void
    {
        $this->warning = new NoEquipmentWarning();
    }

    public function testReturnsNullWhenBothPresent(): void
    {
        $users = [$this->user(volleyball: 1, net: 1)];

        $this->assertNull($this->warning->check($users));
    }

    public function testReturnsNetWarningWhenOnlyNetMissing(): void
    {
        $users = [$this->user(volleyball: 1, net: 0)];

        $this->assertSame('Someone needs to bring a net', $this->warning->check($users));
    }

    public function testReturnsVolleyballWarningWhenOnlyVolleyballMissing(): void
    {
        $users = [$this->user(volleyball: 0, net: 1)];

        $this->assertSame('Someone needs to bring a volleyball', $this->warning->check($users));
    }

    public function testReturnsCombinedWarningWhenBothMissing(): void
    {
        $users = [$this->user(volleyball: 0, net: 0)];

        $this->assertSame('Someone needs to bring a net and a volleyball', $this->warning->check($users));
    }

    public function testChecksAcrossMultipleUsers(): void
    {
        $users = [
            $this->user(volleyball: 0, net: 1),
            $this->user(volleyball: 1, net: 0),
        ];

        $this->assertNull($this->warning->check($users));
    }

    private function user(int $volleyball, int $net): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getVolleyball')->willReturn($volleyball);
        $user->method('getNet')->willReturn($net);

        return $user;
    }
}
