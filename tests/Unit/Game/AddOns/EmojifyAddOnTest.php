<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns;

use BeachVolleybot\Game\AddOns\EmojifyAddOn;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
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
        $game = $this->transform($this->game(title: 'Beach Game 18:00'));

        $this->assertSame('Beach Game 1️⃣8️⃣:0️⃣0️⃣', $game->getTitle());
    }

    public function testReplacesArbitraryMinutesInTitle(): void
    {
        $game = $this->transform($this->game(title: 'Beach Game 18:45'));

        $this->assertSame('Beach Game 1️⃣8️⃣:4️⃣5️⃣', $game->getTitle());
    }

    public function testReplacesSingleDigitHourInTitle(): void
    {
        $game = $this->transform($this->game(title: 'Beach Game 9:30'));

        $this->assertSame('Beach Game 9️⃣:3️⃣0️⃣', $game->getTitle());
    }

    public function testTitleWithoutTimeThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->transform($this->game(title: 'Sunday Beach Game'));
    }

    public function testPreservesTextAroundTime(): void
    {
        $game = $this->transform($this->game(title: 'Saturday Bogatell 10:15 volleyball'));

        $this->assertSame('Saturday Bogatell 1️⃣0️⃣:1️⃣5️⃣ volleyball', $game->getTitle());
    }

    // --- Player times ---

    public function testReplacesPlayerTime(): void
    {
        $game = $this->transform($this->game(players: [
            $this->player(time: '19:00'),
        ]));

        $this->assertSame('19:00', $game->getPlayers()[0]->getTime());
    }

    public function testReplacesPlayerTimeWithArbitraryMinutes(): void
    {
        $game = $this->transform($this->game(players: [
            $this->player(time: '07:15'),
        ]));

        $this->assertSame('07:15', $game->getPlayers()[0]->getTime());
    }

    public function testNullPlayerTimeStaysNull(): void
    {
        $game = $this->transform($this->game(players: [
            $this->player(time: null),
        ]));

        $this->assertNull($game->getPlayers()[0]->getTime());
    }

    public function testPlayerAttributesPreserved(): void
    {
        $game = $this->transform($this->game(players: [
            $this->player(telegramUserId: 42, number: '3', name: 'Alice', link: 'https://t.me/alice', volleyball: 2, net: 1, time: '20:00'),
        ]));

        $player = $game->getPlayers()[0];

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
        $game = $this->transform($this->game(gameId: 42, title: 'Sunday Game 18:00'));

        $this->assertSame(42, $game->getGameId());
        $this->assertSame('query_1', $game->getInlineQueryId());
        $this->assertSame('msg_1', $game->getInlineMessageId());
    }

    // --- Helpers ---

    private function transform(GameInterface $game): GameInterface
    {
        return $this->addOn->transform($game);
    }

    private function game(
        int $gameId = 1,
        string $title = 'Beach Game 18:00',
        array $players = [],
    ): GameInterface {
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
