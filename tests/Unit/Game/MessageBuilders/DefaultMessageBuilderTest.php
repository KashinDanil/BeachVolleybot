<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\MessageBuilders;

use BeachVolleybot\Game\MessageBuilders\DefaultMessageBuilder;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use PHPUnit\Framework\TestCase;

final class DefaultMessageBuilderTest extends TestCase
{
    private const string SEPARATOR = "\n\n";

    private DefaultMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new DefaultMessageBuilder();
    }

    // --- Text: structure ---

    public function testHeaderOnlyWhenNoPlayersAndNoFooter(): void
    {
        $game = $this->game('Beach Game 18:00', []);

        $this->assertSame('Beach Game 18:00', $this->builder->build($game)->text);
    }

    public function testHeaderAndPlayersSeparatedByNewline(): void
    {
        $game = $this->game('Beach Game 18:00', [
            $this->player('1', 'Alice'),
        ]);

        $text = $this->builder->build($game)->text;

        $this->assertSame('Beach Game 18:00' . self::SEPARATOR . '1. Alice', $text);
    }

    public function testFooterAppendedWhenPresent(): void
    {
        $game = $this->game('Beach Game 18:00', [], 'See you there!');

        $text = $this->builder->build($game)->text;

        $this->assertSame('Beach Game 18:00' . self::SEPARATOR . 'See you there!', $text);
    }

    public function testFullMessageWithHeaderPlayersAndFooter(): void
    {
        $game = $this->game('Beach Game 18:00', [
            $this->player('1', 'Alice'),
            $this->player('2', 'Bob'),
        ], 'See you there!');

        $text = $this->builder->build($game)->text;

        $this->assertSame('Beach Game 18:00' . self::SEPARATOR . "1. Alice\n2. Bob" . self::SEPARATOR . 'See you there!', $text);
    }

    // --- Text: player name and link ---

    public function testPlayerNameWithoutLink(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice'),
        ]);

        $this->assertStringContainsString('1. Alice', $this->builder->build($game)->text);
    }

    public function testPlayerNameWithLinkRendersMarkdown(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', link: 'https://t.me/alice'),
        ]);

        $this->assertStringContainsString('1. [Alice](https://t.me/alice)', $this->builder->build($game)->text);
    }

    // --- Text: +N for repeated players ---

    public function testFirstAppearanceShowsPlainName(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice'),
        ]);

        $text = $this->builder->build($game)->text;

        $this->assertStringContainsString('1. Alice', $text);
        $this->assertStringNotContainsString("+", $text);
    }

    public function testSecondAppearanceShowsPlusOne(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice'),
            $this->player('2', 'Alice'),
        ]);

        $text = $this->builder->build($game)->text;

        $this->assertStringContainsString("1. Alice", $text);
        $this->assertStringContainsString("2. Alice's +1", $text);
    }

    public function testThirdAppearanceShowsPlusTwo(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice'),
            $this->player('2', 'Alice'),
            $this->player('3', 'Alice'),
        ]);

        $text = $this->builder->build($game)->text;

        $this->assertStringContainsString("3. Alice's +2", $text);
    }

    public function testPlusNWithLinkedName(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', link: 'https://t.me/alice'),
            $this->player('2', 'Alice', link: 'https://t.me/alice'),
        ]);

        $text = $this->builder->build($game)->text;

        $this->assertStringContainsString("[Alice](https://t.me/alice)'s +1", $text);
    }

    public function testSameNameDifferentLinkTreatedAsDifferentPlayers(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', link: 'https://t.me/alice1'),
            $this->player('2', 'Alice', link: 'https://t.me/alice2'),
        ]);

        $text = $this->builder->build($game)->text;

        $this->assertStringNotContainsString("+1", $text);
    }

    // --- Text: volleyball emoji ---

    public function testZeroVolleyballsShowsNoEmoji(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', volleyball: 0),
        ]);

        $this->assertStringNotContainsString('🏐', $this->builder->build($game)->text);
    }

    public function testOneVolleyballShowsSingleEmoji(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', volleyball: 1),
        ]);

        $this->assertStringContainsString('1. Alice 🏐', $this->builder->build($game)->text);
    }

    public function testTwoVolleyballsShowsTwoEmojis(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', volleyball: 2),
        ]);

        $this->assertStringContainsString('🏐🏐', $this->builder->build($game)->text);
    }

    public function testThreeVolleyballsShowsCompactFormat(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', volleyball: 3),
        ]);

        $this->assertStringContainsString('🏐x3', $this->builder->build($game)->text);
    }

    public function testFiveVolleyballsShowsCompactFormat(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', volleyball: 5),
        ]);

        $this->assertStringContainsString('🏐x5', $this->builder->build($game)->text);
    }

    // --- Text: net emoji ---

    public function testZeroNetsShowsNoEmoji(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', net: 0),
        ]);

        $this->assertStringNotContainsString('🕸️', $this->builder->build($game)->text);
    }

    public function testOneNetShowsSingleEmoji(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', net: 1),
        ]);

        $this->assertStringContainsString('🕸️', $this->builder->build($game)->text);
    }

    public function testTwoNetsShowsTwoEmojis(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', net: 2),
        ]);

        $this->assertStringContainsString('🕸️🕸️', $this->builder->build($game)->text);
    }

    public function testThreeNetsShowsCompactFormat(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', net: 3),
        ]);

        $this->assertStringContainsString('🕸️x3', $this->builder->build($game)->text);
    }

    // --- Text: time ---

    public function testPlayerTimeHiddenWhenNull(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', time: null),
        ]);

        $this->assertSame('Game 18:00' . self::SEPARATOR . '1. Alice', $this->builder->build($game)->text);
    }

    public function testPlayerTimeHiddenWhenMatchesGameTime(): void
    {
        $game = $this->game('Game', [
            $this->player('1', 'Alice', time: '18:00'),
        ], gameTime: '18:00');

        $text = $this->builder->build($game)->text;

        $this->assertSame('Game' . self::SEPARATOR . '1. Alice', $text);
    }

    public function testPlayerTimeShownWhenDifferentFromGameTime(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', time: '19:30'),
        ]);

        $this->assertStringContainsString('19:30', $this->builder->build($game)->text);
    }

    // --- Text: combined player line ---

    public function testFullPlayerLineWithAllAttributes(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('1', 'Alice', link: 'https://t.me/alice', volleyball: 1, net: 2, time: '19:00'),
        ]);

        $this->assertStringContainsString(
            '1. [Alice](https://t.me/alice) 🏐 🕸️🕸️ 19:00',
            $this->builder->build($game)->text
        );
    }

    public function testRangeNumberFormat(): void
    {
        $game = $this->game('Game 18:00', [
            $this->player('4-7', 'Alice'),
        ]);

        $this->assertStringContainsString('4-7. Alice', $this->builder->build($game)->text);
    }

    // --- Keyboard: structure ---

    public function testKeyboardHasThreeRows(): void
    {
        $game = $this->game('Game 18:00', []);
        $keyboard = $this->builder->build($game)->keyboard;

        $this->assertCount(3, $keyboard);
    }

    public function testEachRowHasTwoButtons(): void
    {
        $game = $this->game('Game 18:00', []);
        $keyboard = $this->builder->build($game)->keyboard;

        foreach ($keyboard as $row) {
            $this->assertCount(2, $row);
        }
    }

    // --- Keyboard: button labels ---

    public function testButtonLabels(): void
    {
        $game = $this->game('Game 18:00', []);
        $keyboard = $this->builder->build($game)->keyboard;

        $this->assertSame('Sign Out', $keyboard[0][0]['text']);
        $this->assertSame('Sign Up', $keyboard[0][1]['text']);
        $this->assertSame('-🏐', $keyboard[1][0]['text']);
        $this->assertSame('+🏐', $keyboard[1][1]['text']);
        $this->assertSame('-🕸️', $keyboard[2][0]['text']);
        $this->assertSame('+🕸️', $keyboard[2][1]['text']);
    }

    // --- Keyboard: callback data ---

    public function testMetaButtonContainsInlineQueryId(): void
    {
        $game = $this->game('Game 18:00', [], inlineQueryId: 'q_42');
        $keyboard = $this->builder->build($game)->keyboard;

        $data = json_decode($keyboard[0][0]['callback_data'], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('q_42', $data['q']);
    }

    public function testNonMetaButtonsDoNotContainInlineQueryId(): void
    {
        $game = $this->game('Game 18:00', [], inlineQueryId: 'q_42');
        $keyboard = $this->builder->build($game)->keyboard;

        $nonMetaButtons = [
            $keyboard[0][1],
            $keyboard[1][0],
            $keyboard[1][1],
            $keyboard[2][0],
            $keyboard[2][1],
        ];

        foreach ($nonMetaButtons as $button) {
            $data = json_decode($button['callback_data'], true, flags: JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('q', $data);
        }
    }

    public function testButtonActionCodes(): void
    {
        $game = $this->game('Game 18:00', []);
        $keyboard = $this->builder->build($game)->keyboard;

        $expected = [
            [0, 0, 'rp'],
            [0, 1, 'ap'],
            [1, 0, 'rb'],
            [1, 1, 'ab'],
            [2, 0, 'rn'],
            [2, 1, 'an'],
        ];

        foreach ($expected as [$row, $col, $action]) {
            $data = json_decode($keyboard[$row][$col]['callback_data'], true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($action, $data['a'], "Button [$row][$col] should have action '$action'");
        }
    }

    // --- Helpers ---

    private function player(
        string $number = '1',
        string $name = 'Player',
        ?string $link = null,
        int $volleyball = 0,
        int $net = 0,
        ?string $time = null,
    ): PlayerInterface {
        $player = $this->createStub(PlayerInterface::class);
        $player->method('getNumber')->willReturn($number);
        $player->method('getName')->willReturn($name);
        $player->method('getLink')->willReturn($link);
        $player->method('getVolleyball')->willReturn($volleyball);
        $player->method('getNet')->willReturn($net);
        $player->method('getTime')->willReturn($time);

        return $player;
    }

    private function game(
        string $header,
        array $players,
        ?string $footer = null,
        string $gameTime = '18:00',
        int $gameId = 1,
        string $inlineQueryId = 'query_1',
    ): GameInterface {
        $game = $this->createStub(GameInterface::class);
        $game->method('getGameId')->willReturn($gameId);
        $game->method('getInlineQueryId')->willReturn($inlineQueryId);
        $game->method('getTitle')->willReturn($header);
        $game->method('getPlayers')->willReturn($players);
        $game->method('getFooter')->willReturn($footer);
        $game->method('getTime')->willReturn($gameTime);

        return $game;
    }
}
