<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Telegram\MessageBuilders\PlayersListMessageBuilder;
use PHPUnit\Framework\TestCase;

final class PlayersListMessageBuilderTest extends TestCase
{
    private PlayersListMessageBuilder $builder;

    public function testBuildShowsGameIdInHeader(): void
    {
        $game = $this->createGameWithPlayers(42, 'Friday Game 18:00', []);

        $message = $this->builder->build($game, 1);

        $this->assertStringContainsString('#42', $message->getText()->getMessageText());
    }

    private function createGameWithPlayers(int $gameId, string $title, array $players): GameInterface
    {
        $game = $this->createStub(GameInterface::class);
        $game->method('getGameId')->willReturn($gameId);
        $game->method('getTitle')->willReturn($title);
        $game->method('getPlayers')->willReturn($players);

        return $game;
    }

    public function testBuildShowsGameTitle(): void
    {
        $game = $this->createGameWithPlayers(1, 'Friday Game 18:00', []);

        $message = $this->builder->build($game, 1);

        $this->assertStringContainsString('Friday Game 18:00', $message->getText()->getMessageText());
    }

    public function testBuildShowsPlayerButtons(): void
    {
        $game = $this->createGameWithPlayers(1, 'Game 18:00', [
            $this->createPlayer(100, 'Alice'),
            $this->createPlayer(200, 'Bob'),
        ]);

        $message = $this->builder->build($game, 1);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Alice', $keyboard[0][0]['text']);
        $this->assertSame('Bob', $keyboard[1][0]['text']);
    }

    private function createPlayer(int $telegramUserId, string $name): Player
    {
        return new Player(
            telegramUserId: $telegramUserId,
            number: '1',
            name: $name,
            link: null,
            volleyball: 0,
            net: 0,
            time: null,
        );
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    public function testBuildShowsSlotCountForMultipleSlots(): void
    {
        $game = $this->createGameWithPlayers(1, 'Game 18:00', [
            $this->createPlayer(100, 'Alice'),
            $this->createPlayer(100, 'Alice'),
            $this->createPlayer(200, 'Bob'),
        ]);

        $message = $this->builder->build($game, 1);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Alice (x2)', $keyboard[0][0]['text']);
        $this->assertSame('Bob', $keyboard[1][0]['text']);
    }

    public function testBuildHasPaginationOnMultiplePages(): void
    {
        $players = [];
        for ($i = 1; $i <= 10; $i++) {
            $players[] = $this->createPlayer($i, "Player$i");
        }
        $game = $this->createGameWithPlayers(1, 'Game 18:00', $players);

        $message = $this->builder->build($game, 1);
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertContains('Next >>', $allButtonTexts);
    }

    // --- helpers ---

    private function flattenButtonTexts(array $keyboard): array
    {
        $texts = [];
        foreach ($keyboard as $row) {
            foreach ($row as $button) {
                $texts[] = $button['text'];
            }
        }

        return $texts;
    }

    public function testBuildHasBackButton(): void
    {
        $game = $this->createGameWithPlayers(1, 'Game 18:00', []);

        $message = $this->builder->build($game, 1);
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    public function testBuildShowsPageInfo(): void
    {
        $game = $this->createGameWithPlayers(1, 'Game 18:00', []);

        $message = $this->builder->build($game, 1);

        $this->assertStringContainsString('Page 1 of 1', $message->getText()->getMessageText());
    }

    protected function setUp(): void
    {
        $this->builder = new PlayersListMessageBuilder();
    }
}
