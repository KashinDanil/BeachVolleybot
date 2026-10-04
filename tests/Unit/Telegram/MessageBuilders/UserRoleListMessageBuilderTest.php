<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\Admin\UserRoleListMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\KeyboardPagination;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\User\Role;
use PHPUnit\Framework\TestCase;

final class UserRoleListMessageBuilderTest extends TestCase
{
    use CreatesUserRecords;

    private const int PAGE_SIZE = 8;

    private UserRoleListMessageBuilder $builder;

    public function testBuildShowsHeader(): void
    {
        $message = $this->builder->build([], $this->pagination(0, 1));

        $this->assertStringContainsString('Users', $message->getText()->getMessageText());
    }

    public function testBuildShowsUserButtonsWithRoleName(): void
    {
        $users = [
            $this->userRecord(100, 'Alice', role: Role::Player),
            $this->userRecord(200, 'Bob', role: Role::Admin),
        ];

        $message = $this->builder->build($users, $this->pagination(2, 1));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Alice — Player', $keyboard[0][0]['text']);
        $this->assertSame('Bob — Admin', $keyboard[1][0]['text']);
    }

    public function testBuildStylesUserButtonsByRole(): void
    {
        $users = [
            $this->userRecord(100, 'Rita', role: Role::Root),
            $this->userRecord(200, 'Adam', role: Role::Admin),
            $this->userRecord(300, 'Pola', role: Role::Player),
        ];

        $message = $this->builder->build($users, $this->pagination(3, 1));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('primary', $keyboard[0][0]['style']);
        $this->assertSame('danger', $keyboard[1][0]['style']);
        $this->assertArrayNotHasKey('style', $keyboard[2][0]);
    }

    public function testBuildHasPaginationOnMultiplePages(): void
    {
        $users = [];
        for ($i = 1; $i <= self::PAGE_SIZE; $i++) {
            $users[] = $this->userRecord($i, "User$i", role: Role::Player);
        }

        $message = $this->builder->build($users, $this->pagination(self::PAGE_SIZE + 2, 1));
        $keyboard = $this->extractKeyboard($message);

        $this->assertContains('Next »', $this->flattenButtonTexts($keyboard));
    }

    public function testBuildHasBackButton(): void
    {
        $message = $this->builder->build([], $this->pagination(0, 1));
        $keyboard = $this->extractKeyboard($message);

        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    public function testBuildShowsPageInfo(): void
    {
        $message = $this->builder->build([$this->userRecord(100, 'Alice', role: Role::Player)], $this->pagination(1, 1));

        $this->assertStringContainsString('Page 1 of 1', $message->getText()->getMessageText());
    }

    private function pagination(int $totalUsers, int $page): KeyboardPagination
    {
        return new KeyboardPagination($totalUsers, self::PAGE_SIZE, $page);
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

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

    protected function setUp(): void
    {
        $this->builder = new UserRoleListMessageBuilder();
    }
}
