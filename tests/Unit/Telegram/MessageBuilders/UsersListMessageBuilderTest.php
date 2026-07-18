<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\UsersListMessageBuilder;
use PHPUnit\Framework\TestCase;

final class UsersListMessageBuilderTest extends TestCase
{
    private UsersListMessageBuilder $builder;

    public function testBuildShowsGameIdInHeader(): void
    {
        $game = $this->createGameWithUsers(42, 'Friday Game 18:00', []);

        $message = $this->builder->build($game, 1);

        $this->assertStringContainsString('#42', $message->getText()->getMessageText());
    }

    private function createGameWithUsers(int $gameId, string $title, array $users): GameInterface
    {
        $game = $this->createStub(GameInterface::class);
        $game->method('getGameId')->willReturn($gameId);
        $game->method('getTitle')->willReturn($title);
        $game->method('getUsers')->willReturn($users);

        return $game;
    }

    public function testBuildShowsGameTitle(): void
    {
        $game = $this->createGameWithUsers(1, 'Friday Game 18:00', []);

        $message = $this->builder->build($game, 1);

        $this->assertStringContainsString('Friday Game 18:00', $message->getText()->getMessageText());
    }

    public function testBuildWrapsGameTitleInBlockquote(): void
    {
        $game = $this->createGameWithUsers(1, 'Friday Game 18:00', []);

        $message = $this->builder->build($game, 1);

        $formatter = new MarkdownV2();
        $expectedTitleLine = $formatter->blockquote($formatter->escape('Friday Game 18:00'));
        $this->assertStringContainsString($expectedTitleLine, $message->getText()->getMessageText());
    }

    public function testBuildShowsUserButtons(): void
    {
        $game = $this->createGameWithUsers(1, 'Game 18:00', [
            $this->createUser(100, 'Alice'),
            $this->createUser(200, 'Bob'),
        ]);

        $message = $this->builder->build($game, 1);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Alice', $keyboard[0][0]['text']);
        $this->assertSame('Bob', $keyboard[1][0]['text']);
    }

    private function createUser(int $telegramUserId, string $name): User
    {
        return new User(
            telegramUserId: $telegramUserId,
            number: '1',
            name: $name,
            link: null,
            volleyball: 0,
            net: 0,
            time: '18:00',
        );
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    public function testBuildShowsSlotCountForMultipleSlots(): void
    {
        $game = $this->createGameWithUsers(1, 'Game 18:00', [
            $this->createUser(100, 'Alice'),
            $this->createUser(100, 'Alice'),
            $this->createUser(200, 'Bob'),
        ]);

        $message = $this->builder->build($game, 1);
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Alice (x2)', $keyboard[0][0]['text']);
        $this->assertSame('Bob', $keyboard[1][0]['text']);
    }

    public function testBuildHasPaginationOnMultiplePages(): void
    {
        $users = [];
        for ($i = 1; $i <= 10; $i++) {
            $users[] = $this->createUser($i, "User$i");
        }
        $game = $this->createGameWithUsers(1, 'Game 18:00', $users);

        $message = $this->builder->build($game, 1);
        $keyboard = $this->extractKeyboard($message);

        $allButtonTexts = $this->flattenButtonTexts($keyboard);
        $this->assertContains('Next »', $allButtonTexts);
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
        $game = $this->createGameWithUsers(1, 'Game 18:00', []);

        $message = $this->builder->build($game, 1);
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    public function testBuildShowsPageInfo(): void
    {
        $game = $this->createGameWithUsers(1, 'Game 18:00', []);

        $message = $this->builder->build($game, 1);

        $this->assertStringContainsString('Page 1 of 1', $message->getText()->getMessageText());
    }

    protected function setUp(): void
    {
        $this->builder = new UsersListMessageBuilder();
    }
}
