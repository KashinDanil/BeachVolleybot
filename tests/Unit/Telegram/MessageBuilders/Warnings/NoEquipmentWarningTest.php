<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoEquipmentWarning;
use PHPUnit\Framework\TestCase;

final class NoEquipmentWarningTest extends TestCase
{
    private NoEquipmentWarning $warning;

    private Translator $translator;

    protected function setUp(): void
    {
        $this->warning = new NoEquipmentWarning();
        $this->translator = new Translator();
    }

    public function testReturnsNullWhenBothPresent(): void
    {
        $game = $this->game($this->user(volleyball: 1, net: 1));

        $this->assertNull($this->warning->check($game, $this->translator));
    }

    public function testReturnsNetWarningWhenOnlyNetMissing(): void
    {
        $game = $this->game($this->user(volleyball: 1, net: 0));

        $this->assertSame('Someone needs to bring a net', $this->warning->check($game, $this->translator));
    }

    public function testReturnsVolleyballWarningWhenOnlyVolleyballMissing(): void
    {
        $game = $this->game($this->user(volleyball: 0, net: 1));

        $this->assertSame('Someone needs to bring a volleyball', $this->warning->check($game, $this->translator));
    }

    public function testReturnsCombinedWarningWhenBothMissing(): void
    {
        $game = $this->game($this->user(volleyball: 0, net: 0));

        $this->assertSame('Someone needs to bring a net and a volleyball', $this->warning->check($game, $this->translator));
    }

    public function testChecksAcrossMultipleUsers(): void
    {
        $game = $this->game(
            $this->user(volleyball: 0, net: 1),
            $this->user(volleyball: 1, net: 0),
        );

        $this->assertNull($this->warning->check($game, $this->translator));
    }

    private function game(UserInterface ...$users): GameInterface
    {
        $game = $this->createStub(GameInterface::class);
        $game->method('getUsers')->willReturn($users);

        return $game;
    }

    private function user(int $volleyball, int $net): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getVolleyball')->willReturn($volleyball);
        $user->method('getNet')->willReturn($net);

        return $user;
    }
}
