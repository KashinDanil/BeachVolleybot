<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Tests\Unit\Game\AddOns\Stub\TitlePrefixAddOn;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameAddOnTest extends TestCase
{
    // --- Single add-on ---

    public function testAddOnModifiesTitle(): void
    {
        $game = $this->game();

        (new TitlePrefixAddOn())->applyTo($game);

        $this->assertSame('[Modified] Beach Game 18:00', $game->title);
    }

    public function testAddOnPreservesGameId(): void
    {
        $game = $this->game(gameId: 42);

        (new TitlePrefixAddOn())->applyTo($game);

        $this->assertSame(42, $game->getGameId());
    }

    public function testAddOnPreservesPlayers(): void
    {
        $game = $this->game();
        $playersBefore = $game->players;

        (new TitlePrefixAddOn())->applyTo($game);

        $this->assertSame($playersBefore, $game->players);
    }

    // --- Custom constructor parameter ---

    public function testAddOnWithCustomPrefix(): void
    {
        $game = $this->game();

        (new TitlePrefixAddOn('[VIP]'))->applyTo($game);

        $this->assertSame('[VIP] Beach Game 18:00', $game->title);
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
            $addOn->applyTo($game);
        }

        $this->assertSame('[Second] [First] Beach Game 18:00', $game->title);
    }

    // --- Helpers ---

    private function game(
        int $gameId = 1,
        string $title = 'Beach Game 18:00',
    ): Game {
        return new Game(
            gameId: $gameId,
            inlineQueryId: 'query_1',
            inlineMessageId: 'msg_1',
            title: $title,
            players: [],
            createdAt: new DateTimeImmutable(),
        );
    }
}
