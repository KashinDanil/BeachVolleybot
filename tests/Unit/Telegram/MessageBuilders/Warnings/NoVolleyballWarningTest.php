<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoVolleyballWarning;
use PHPUnit\Framework\TestCase;

final class NoVolleyballWarningTest extends TestCase
{
    private NoVolleyballWarning $warning;

    protected function setUp(): void
    {
        $this->warning = new NoVolleyballWarning();
    }

    public function testReturnsNullWhenAtLeastOnePlayerHasVolleyball(): void
    {
        $players = [
            $this->player(volleyball: 0),
            $this->player(volleyball: 1),
        ];

        $this->assertNull($this->warning->check($players));
    }

    public function testReturnsWarningWhenNoPlayerHasVolleyball(): void
    {
        $players = [
            $this->player(volleyball: 0),
            $this->player(volleyball: 0),
        ];

        $this->assertSame('A volleyball is needed', $this->warning->check($players));
    }

    public function testReturnsWarningForSinglePlayerWithoutVolleyball(): void
    {
        $players = [
            $this->player(volleyball: 0),
        ];

        $this->assertSame('A volleyball is needed', $this->warning->check($players));
    }

    private function player(int $volleyball): PlayerInterface
    {
        $player = $this->createStub(PlayerInterface::class);
        $player->method('getVolleyball')->willReturn($volleyball);

        return $player;
    }
}
