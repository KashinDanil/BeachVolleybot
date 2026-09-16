<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\GameWarningCollector;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoEquipmentWarning;
use PHPUnit\Framework\TestCase;

final class GameWarningCollectorTest extends TestCase
{
    private GameWarningCollector $collector;

    private Translator $translator;

    protected function setUp(): void
    {
        $this->collector = new GameWarningCollector(
            new NoEquipmentWarning(),
        );
        $this->translator = new Translator();
    }

    public function testReturnsEmptyArrayWhenUsersHaveEquipment(): void
    {
        $game = $this->game($this->user(volleyball: 1, net: 1));

        $this->assertSame([], $this->collector->collect($game, $this->translator));
    }

    public function testReturnsWarningWhenNetMissing(): void
    {
        $game = $this->game($this->user(volleyball: 1, net: 0));

        $this->assertSame(['Someone needs to bring a net'], $this->collector->collect($game, $this->translator));
    }

    public function testReturnsWarningWhenVolleyballMissing(): void
    {
        $game = $this->game($this->user(volleyball: 0, net: 1));

        $this->assertSame(['Someone needs to bring a volleyball'], $this->collector->collect($game, $this->translator));
    }

    public function testReturnsCombinedWarningWhenBothMissing(): void
    {
        $game = $this->game($this->user(volleyball: 0, net: 0));

        $this->assertSame(
            ['Someone needs to bring a net and a volleyball'],
            $this->collector->collect($game, $this->translator),
        );
    }

    public function testReturnsEmptyArrayWhenNoWarnings(): void
    {
        $collector = new GameWarningCollector();
        $game = $this->game($this->user(volleyball: 0, net: 0));

        $this->assertSame([], $collector->collect($game, $this->translator));
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
