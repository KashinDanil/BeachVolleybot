<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Tests\Unit\Game\AddOns\Stub\TitlePrefixAddOn;
use PHPUnit\Framework\TestCase;

final class GameAddOnTest extends TestCase
{
    // --- Single add-on ---

    public function testAddOnModifiesTitle(): void
    {
        $game = $this->game();

        $result = new TitlePrefixAddOn()->transform($game);

        $this->assertSame('[Modified] Beach Game 18:00', $result->getTitle());
    }

    public function testAddOnPreservesGameId(): void
    {
        $game = $this->game(gameId: 42);

        $result = new TitlePrefixAddOn()->transform($game);

        $this->assertSame(42, $result->getGameId());
    }

    public function testAddOnPreservesPlayers(): void
    {
        $game = $this->game();

        $result = new TitlePrefixAddOn()->transform($game);

        $this->assertSame($game->getPlayers(), $result->getPlayers());
    }

    // --- Custom constructor parameter ---

    public function testAddOnWithCustomPrefix(): void
    {
        $game = $this->game();

        $result = new TitlePrefixAddOn('[VIP]')->transform($game);

        $this->assertSame('[VIP] Beach Game 18:00', $result->getTitle());
    }

    // --- Chaining multiple add-ons ---

    public function testMultipleAddOnsAppliedInSequence(): void
    {
        $game = $this->game();

        $addOns = [
            new TitlePrefixAddOn('[First]'),
            new TitlePrefixAddOn('[Second]'),
        ];

        foreach ($addOns as $addOn) {
            $game = $addOn->transform($game);
        }

        $this->assertSame('[Second] [First] Beach Game 18:00', $game->getTitle());
    }

    public function testAddOnReturnsNewInstance(): void
    {
        $game = $this->game();

        $result = new TitlePrefixAddOn()->transform($game);

        $this->assertNotSame($game, $result);
    }

    // --- Helpers ---

    private function game(
        int $gameId = 1,
        string $title = 'Beach Game 18:00',
    ): GameInterface {
        return new Game(
            gameId: $gameId,
            inlineQueryId: 'query_1',
            inlineMessageId: 'msg_1',
            title: $title,
            players: [],
        );
    }
}