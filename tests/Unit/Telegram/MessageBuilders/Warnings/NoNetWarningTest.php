<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoNetWarning;
use PHPUnit\Framework\TestCase;

final class NoNetWarningTest extends TestCase
{
    private NoNetWarning $warning;

    protected function setUp(): void
    {
        $this->warning = new NoNetWarning();
    }

    public function testReturnsNullWhenAtLeastOnePlayerHasNet(): void
    {
        $players = [
            $this->player(net: 0),
            $this->player(net: 1),
        ];

        $this->assertNull($this->warning->check($players));
    }

    public function testReturnsWarningWhenNoPlayerHasNet(): void
    {
        $players = [
            $this->player(net: 0),
            $this->player(net: 0),
        ];

        $this->assertSame('Someone needs to bring a net', $this->warning->check($players));
    }

    public function testReturnsWarningForSinglePlayerWithoutNet(): void
    {
        $players = [
            $this->player(net: 0),
        ];

        $this->assertSame('Someone needs to bring a net', $this->warning->check($players));
    }

    private function player(int $net): PlayerInterface
    {
        $player = $this->createStub(PlayerInterface::class);
        $player->method('getNet')->willReturn($net);

        return $player;
    }
}
