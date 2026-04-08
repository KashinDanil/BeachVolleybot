<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns;

use BeachVolleybot\Game\AddOns\EmojifyAddOn;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\Player;
use PHPUnit\Framework\TestCase;

final class EmojifyAddOnTest extends TestCase
{
    private EmojifyAddOn $addOn;

    protected function setUp(): void
    {
        $this->addOn = new EmojifyAddOn();
    }

    // --- Title ---

    public function testReplacesTimeInTitle(): void
    {
        $game = $this->game(title: 'Beach Game 18:00');

        $this->transform($game);

        $this->assertSame('Beach Game 1️⃣8️⃣:0️⃣0️⃣', $game->title);
    }

    public function testReplacesArbitraryMinutesInTitle(): void
    {
        $game = $this->game(title: 'Beach Game 18:45');

        $this->transform($game);

        $this->assertSame('Beach Game 1️⃣8️⃣:4️⃣5️⃣', $game->title);
    }

    public function testReplacesSingleDigitHourInTitle(): void
    {
        $game = $this->game(title: 'Beach Game 9:30');

        $this->transform($game);

        $this->assertSame('Beach Game 9️⃣:3️⃣0️⃣', $game->title);
    }

    public function testPreservesTextAroundTime(): void
    {
        $game = $this->game(title: 'Saturday Bogatell 10:15 volleyball');

        $this->transform($game);

        $this->assertSame('Saturday Bogatell 1️⃣0️⃣:1️⃣5️⃣ volleyball', $game->title);
    }

    // --- Player times ---

    public function testPlayerTimesUnchanged(): void
    {
        $game = $this->game(players: [
            $this->player(time: '19:00'),
        ]);

        $this->transform($game);

        $this->assertSame('19:00', $game->players[0]->getTime());
    }

    public function testNullPlayerTimeStaysNull(): void
    {
        $game = $this->game(players: [
            $this->player(time: null),
        ]);

        $this->transform($game);

        $this->assertNull($game->players[0]->getTime());
    }

    public function testPlayerAttributesPreserved(): void
    {
        $game = $this->game(players: [
            $this->player(telegramUserId: 42, number: '3', name: 'Alice', link: 'https://t.me/alice', volleyball: 2, net: 1, time: '20:00'),
        ]);

        $this->transform($game);

        $player = $game->players[0];

        $this->assertSame(42, $player->getTelegramUserId());
        $this->assertSame('3', $player->getNumber());
        $this->assertSame('Alice', $player->getName());
        $this->assertSame('https://t.me/alice', $player->getLink());
        $this->assertSame(2, $player->getVolleyball());
        $this->assertSame(1, $player->getNet());
    }

    // --- Game properties preserved ---

    public function testGamePropertiesPreserved(): void
    {
        $game = $this->game(gameId: 42, title: 'Sunday Game 18:00');

        $this->transform($game);

        $this->assertSame(42, $game->getGameId());
        $this->assertSame('query_1', $game->getInlineQueryId());
        $this->assertSame('msg_1', $game->getInlineMessageId());
    }

    // --- Helpers ---

    private function transform(Game $game): void
    {
        $this->addOn->applyTo($game);
    }

    private function game(
        int $gameId = 1,
        string $title = 'Beach Game 18:00',
        array $players = [],
    ): Game {
        return new Game(
            gameId: $gameId,
            inlineQueryId: 'query_1',
            inlineMessageId: 'msg_1',
            title: $title,
            players: $players,
        );
    }

    private function player(
        int $telegramUserId = 1,
        string $number = '1',
        string $name = 'Alice',
        ?string $link = null,
        int $volleyball = 0,
        int $net = 0,
        ?string $time = null,
    ): Player {
        return new Player(
            telegramUserId: $telegramUserId,
            number: $number,
            name: $name,
            link: $link,
            volleyball: $volleyball,
            net: $net,
            time: $time,
        );
    }
}
