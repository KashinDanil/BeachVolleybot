<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Game\GameDetailMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Game\ShareGameMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserRecord;
use PHPUnit\Framework\TestCase;

final class GameDetailMessageBuilderTest extends TestCase
{
    use CreatesUserRecords;

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

    public function testGameDetailShowsUserCount(): void
    {
        $player1 = $this->createPlayerStub(100);
        $player2 = $this->createPlayerStub(200);
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: [$player1, $player2]);

        $message = $this->buildDetail($game);

        $this->assertStringContainsString('Users: 2', $message->getText()->getMessageText());
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
        $this->assertStringContainsString('Users: 2', $message->getText()->getMessageText());
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

    public function testGameDetailHasUsersButton(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Users', $keyboard[1][0]['text']);
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

    public function testGameDetailOmitsCreatorLineWhenCreatorMissing(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game, creator: null);

        $this->assertStringNotContainsString('Creator', $message->getText()->getMessageText());
    }

    public function testGameDetailShowsCreatorNameWithoutLinkWhenNoUsername(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);
        $creator = $this->userRecord(200, 'Danil', lastName: 'Kashin');

        $message = $this->buildDetail($game, creator: $creator);

        $text = $message->getText()->getMessageText();
        $this->assertStringContainsString('Creator: Danil Kashin', $text);
        $this->assertStringNotContainsString('https://t.me/', $text);
    }

    public function testGameDetailShowsCreatorLinkWhenUsernamePresent(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);
        $creator = $this->userRecord(200, 'Danil', username: 'danil_kashin');

        $message = $this->buildDetail($game, creator: $creator);

        $text = $message->getText()->getMessageText();
        $this->assertStringContainsString('Creator: [Danil](https://t.me/danil_kashin)', $text);
    }

    private function buildDetail(
        GameInterface $game,
        ?UserRecord $creator = null,
        bool $sharingEnabled = true,
        Role $viewerRole = Role::Root,
    ): TelegramMessage {
        return $this->builder->buildGameDetail($game, $creator, $viewerRole, $sharingEnabled);
    }

    // --- viewer role ---

    public function testAdminSeesOnlyShareAndBack(): void
    {
        $game = $this->createGameStub(gameId: 42, title: 'Game 18:00', players: [], location: '55.7,37.6');

        $message = $this->buildDetail($game, viewerRole: Role::Admin);
        $keyboard = $this->extractKeyboard($message);

        $buttonTexts = array_map(fn($row) => $row[0]['text'], $keyboard);
        $this->assertSame(['Share', "\u{21A9} Back"], $buttonTexts);
    }

    public function testRootSeesEveryButton(): void
    {
        $game = $this->createGameStub(gameId: 42, title: 'Game 18:00', players: [], location: '55.7,37.6');

        $message = $this->buildDetail($game, viewerRole: Role::Root);
        $keyboard = $this->extractKeyboard($message);

        $buttonTexts = array_map(fn($row) => $row[0]['text'], $keyboard);
        $this->assertSame(['Share', 'Users', 'Remove Location', "\u{21A9} Back"], $buttonTexts);
    }

    public function testPlayerSeesOnlyShareAndBack(): void
    {
        $game = $this->createGameStub(gameId: 42, title: 'Game 18:00', players: [], location: '55.7,37.6');

        $message = $this->buildDetail($game, viewerRole: Role::Player);
        $keyboard = $this->extractKeyboard($message);

        $buttonTexts = array_map(fn($row) => $row[0]['text'], $keyboard);
        $this->assertSame(['Share', "\u{21A9} Back"], $buttonTexts);
    }

    public function testAdminSeesOnlyBackOnPastGame(): void
    {
        $game = $this->createGameStub(gameId: 42, title: 'Game 18:00', players: [], location: '55.7,37.6');

        $message = $this->buildDetail($game, sharingEnabled: false, viewerRole: Role::Admin);
        $keyboard = $this->extractKeyboard($message);

        $buttonTexts = array_map(fn($row) => $row[0]['text'], $keyboard);
        $this->assertSame(["\u{21A9} Back"], $buttonTexts);
    }

    // --- past-kickoff behavior ---

    public function testPastGameOmitsShareButton(): void
    {
        $game = $this->createGameStub(gameId: 42, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game, sharingEnabled: false);
        $keyboard = $this->extractKeyboard($message);

        foreach ($keyboard as $row) {
            foreach ($row as $button) {
                $this->assertArrayNotHasKey('switch_inline_query', $button);
            }
        }
    }

    public function testPastGameShowsFinishedNoticeInBody(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game, sharingEnabled: false);

        $this->assertStringContainsString(
            ShareGameMessageBuilder::DISABLED_NOTICE,
            $message->getText()->getMessageText(),
        );
    }

    public function testFutureGameOmitsFinishedNotice(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game, sharingEnabled: true);

        $this->assertStringNotContainsString(
            ShareGameMessageBuilder::DISABLED_NOTICE,
            $message->getText()->getMessageText(),
        );
    }

    public function testPastGameFirstKeyboardRowIsUsersNotShare(): void
    {
        $game = $this->createGameStub(gameId: 1, title: 'Game 18:00', players: []);

        $message = $this->buildDetail($game, sharingEnabled: false);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Users', $keyboard[0][0]['text']);
    }

    protected function setUp(): void
    {
        $this->builder = new GameDetailMessageBuilder();
    }
}
