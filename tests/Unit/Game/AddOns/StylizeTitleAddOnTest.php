<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns;

use BeachVolleybot\Game\AddOns\StylizeTitleAddOn;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\Player;
use PHPUnit\Framework\TestCase;

final class StylizeTitleAddOnTest extends TestCase
{
    private StylizeTitleAddOn $addOn;

    protected function setUp(): void
    {
        $this->addOn = new StylizeTitleAddOn();
    }

    // --- Time: bold + underline ---

    public function testTimeBecomesUnderline(): void
    {
        $game = $this->game(title: 'Beach Game 18:00');

        $this->transform($game);

        $this->assertSame('Beach Game __18:00__', $this->buildTitle($game));
    }

    public function testSingleDigitHourBecomesUnderline(): void
    {
        $game = $this->game(title: 'Beach Game 9:30');

        $this->transform($game);

        $this->assertSame('Beach Game __9:30__', $this->buildTitle($game));
    }

    // --- Day of week: italic ---

    public function testDayOfWeekBecomesItalic(): void
    {
        $game = $this->game(title: 'Friday Game 18:00');

        $this->transform($game);

        $this->assertSame('_Friday_ Game __18:00__', $this->buildTitle($game));
    }

    public function testDayOfWeekCaseInsensitive(): void
    {
        $game = $this->game(title: 'saturday Game 18:00');

        $this->transform($game);

        $this->assertSame('_saturday_ Game __18:00__', $this->buildTitle($game));
    }

    // --- Date: italic ---

    public function testDateBecomesItalic(): void
    {
        $game = $this->game(title: 'Game 11.04 18:00');

        $this->transform($game);

        $this->assertSame('Game _11\.04_ __18:00__', $this->buildTitle($game));
    }

    public function testDateWithYearBecomesItalic(): void
    {
        $game = $this->game(title: 'Game 11.04.2026 18:00');

        $this->transform($game);

        $this->assertSame('Game _11\.04\.2026_ __18:00__', $this->buildTitle($game));
    }

    // --- Combined ---

    public function testDayDateAndTimeCombined(): void
    {
        $game = $this->game(title: 'Saturday, 11.04 Bogatell 18:00');

        $this->transform($game);

        $this->assertSame('_Saturday_, _11\.04_ Bogatell __18:00__', $this->buildTitle($game));
    }

    // --- No special parts ---

    public function testPlainTitleIsEscaped(): void
    {
        $game = $this->game(title: 'Beach Game');

        $this->transform($game);

        $this->assertSame('Beach Game', $this->buildTitle($game));
    }

    public function testSpecialCharsEscaped(): void
    {
        $game = $this->game(title: 'Game (test) 18:00');

        $this->transform($game);

        $this->assertSame('Game \\(test\\) __18:00__', $this->buildTitle($game));
    }

    // --- Preserves text around styled parts ---

    public function testPreservesTextAroundTime(): void
    {
        $game = $this->game(title: 'Saturday Bogatell 10:15 volleyball');

        $this->transform($game);

        $this->assertSame('_Saturday_ Bogatell __10:15__ volleyball', $this->buildTitle($game));
    }

    // --- Player properties preserved ---

    public function testPlayerTimesUnchanged(): void
    {
        $game = $this->game(players: [
            $this->player(time: '19:00'),
        ]);

        $this->transform($game);

        $this->assertSame('19:00', $game->players[0]->getTime());
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
        $game->init();
        $this->addOn->applyTo($game);
    }

    private function buildTitle(Game $game): string
    {
        return $game->telegramMessageBuilder->buildTitle($game);
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
