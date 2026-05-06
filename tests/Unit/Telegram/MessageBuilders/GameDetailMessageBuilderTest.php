<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\GameDetailMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use PHPUnit\Framework\TestCase;

final class GameDetailMessageBuilderTest extends TestCase
{
    private GameDetailMessageBuilder $builder;

    public function testGameNotFoundContainsGameNotFoundText(): void
    {
        $message = $this->builder->buildGameNotFound();

        $this->assertStringContainsString('Game not found', $message->getText()->getMessageText());
    }

    // --- buildGameNotFound ---

    public function testGameNotFoundHasBackButton(): void
    {
        $message = $this->builder->buildGameNotFound();
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    // --- buildGameDetail ---

    public function testGameDetailShowsGameId(): void
    {
        $game = $this->createGameStub(gameId: 42, title: 'Friday Game 18:00', players: []);

        $message = $this->buildDetail($game);

        $this->assertStringContainsString('#42', $message->getText()->getMessageText());
    }

    private function createGameStub(int $gameId, string $title, array $players, ?string $location = null): GameInterface
    {
        $game = $this->createStub(GameInterface::class);
        $game->method('getGameId')->willReturn($gameId);
        $game->method('getTitle')->willReturn($title);
        $game->method('getPlayers')->willReturn($players);
        $game->method('getLocation')->willReturn($location);

        return $game;
    }

    public function testGameDetailShowsTitle(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Friday Game 18:00', players: []);

        $message = $this->buildDetail($game);

        $this->assertStringContainsString('Friday Game 18:00', $message->getText()->getMessageText());
    }

    public function testGameDetailWrapsTitleInBlockquote(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Friday Game 18:00', players: []);

        $message = $this->buildDetail($game);

        $formatter = new MarkdownV2();
        $expectedTitleLine = $formatter->blockquote($formatter->escape('Friday Game 18:00'));
        $this->assertStringContainsString($expectedTitleLine, $message->getText()->getMessageText());
    }

    public function testGameDetailShowsPlayerCount(): void
    {
        $player1 = $this->createPlayerStub(100);
        $player2 = $this->createPlayerStub(200);
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: [$player1, $player2]);

        $message = $this->buildDetail($game);

        $this->assertStringContainsString('Players: 2', $message->getText()->getMessageText());
    }

    private function createPlayerStub(int $telegramUserId): PlayerInterface
    {
        $player = $this->createStub(PlayerInterface::class);
        $player->method('getTelegramUserId')->willReturn($telegramUserId);

        return $player;
    }

    public function testGameDetailShowsSlotCount(): void
    {
        $player1 = $this->createPlayerStub(100);
        $player2 = $this->createPlayerStub(100);
        $player3 = $this->createPlayerStub(200);
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: [$player1, $player2, $player3]);

        $message = $this->buildDetail($game);

        $this->assertStringContainsString('Slots: 3', $message->getText()->getMessageText());
        $this->assertStringContainsString('Players: 2', $message->getText()->getMessageText());
    }

    public function testGameDetailShowsLocationWhenPresent(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: [], location: '55.7,37.6');

        $message = $this->buildDetail($game);

        $this->assertStringContainsString('Location', $message->getText()->getMessageText());
    }

    public function testGameDetailOmitsLocationWhenNull(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game);

        $this->assertStringNotContainsString('Location', $message->getText()->getMessageText());
    }

    public function testGameDetailHasShareButtonAsFirstRow(): void
    {
        $game = $this->createGameStub(gameId: 42, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Share', $keyboard[0][0]['text']);
        $this->assertSame('Forward game 42', $keyboard[0][0]['switch_inline_query']);
    }

    public function testGameDetailHasPlayersButton(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Players', $keyboard[1][0]['text']);
    }

    public function testGameDetailShowsRemoveLocationWhenLocationPresent(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: [], location: '55.7,37.6');

        $message = $this->buildDetail($game);
        $keyboard = $this->extractKeyboard($message);

        $buttonTexts = array_map(fn($row) => $row[0]['text'], $keyboard);
        $this->assertContains('Remove Location', $buttonTexts);
    }

    // --- helpers ---

    public function testGameDetailHidesRemoveLocationWhenNoLocation(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game);
        $keyboard = $this->extractKeyboard($message);

        $buttonTexts = array_map(fn($row) => $row[0]['text'], $keyboard);
        $this->assertNotContains('Remove Location', $buttonTexts);
    }

    public function testGameDetailHasBackButton(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game);
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    // --- creator line ---

    public function testGameDetailOmitsCreatorLineWhenCreatorRowMissing(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game, creatorRow: null);

        $this->assertStringNotContainsString('Creator', $message->getText()->getMessageText());
    }

    public function testGameDetailShowsCreatorNameWithoutLinkWhenNoUsername(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);
        $creatorRow = ['first_name' => 'Danil', 'last_name' => 'Kashin', 'username' => null];

        $message = $this->buildDetail($game, creatorRow: $creatorRow);

        $text = $message->getText()->getMessageText();
        $this->assertStringContainsString('Danil Kashin', $text);
        $this->assertStringNotContainsString('https://t.me/', $text);
    }

    public function testGameDetailShowsCreatorLinkWhenUsernamePresent(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);
        $creatorRow = ['first_name' => 'Danil', 'last_name' => null, 'username' => 'danil_kashin'];

        $message = $this->buildDetail($game, creatorRow: $creatorRow);

        $text = $message->getText()->getMessageText();
        $this->assertStringContainsString('Danil', $text);
        $this->assertStringContainsString('https://t.me/danil_kashin', $text);
    }

    private function buildDetail(GameInterface $game, ?array $creatorRow = null): TelegramMessage
    {
        return $this->builder->buildGameDetail($game, $creatorRow);
    }

    protected function setUp(): void
    {
        $this->builder = new GameDetailMessageBuilder();
    }
}
