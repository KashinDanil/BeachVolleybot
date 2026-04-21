<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns;

use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Tests\Unit\Game\AddOns\Stub\TitlePrefixAddOn;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameAddOnApplierTest extends TestCase
{
    public function testAddOnApplied(): void
    {
        $game = $this->game();

        GameAddOnApplier::apply($game, [TitlePrefixAddOn::class]);

        $this->assertSame('[Modified] Beach Game 18:00', $game->getTitle());
    }

    public function testMultipleAddOnsAppliedInOrder(): void
    {
        $game = $this->game();

        GameAddOnApplier::apply($game, [TitlePrefixAddOn::class, TitlePrefixAddOn::class]);

        $this->assertSame('[Modified] [Modified] Beach Game 18:00', $game->getTitle());
    }

    public function testNoAddOnsLeavesGameUnchanged(): void
    {
        $game = $this->game();

        GameAddOnApplier::apply($game, []);

        $this->assertSame('Beach Game 18:00', $game->getTitle());
    }

    // --- Helpers ---

    private function game(): Game
    {
        return new Game(
            gameId: 1,
            inlineQueryId: 'query_1',
            inlineMessageId: 'msg_1',
            title: 'Beach Game 18:00',
            players: [],
            createdAt: new DateTimeImmutable(),
        );
    }
}
