<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\KeyboardPagination;
use BeachVolleybot\Telegram\MessageBuilders\UserRoleListMessageBuilder;
use BeachVolleybot\User\Role;
use PHPUnit\Framework\TestCase;

final class UserRoleListMessageBuilderTest extends TestCase
{
    private const int PAGE_SIZE = 8;

    private UserRoleListMessageBuilder $builder;

    public function testBuildShowsHeader(): void
    {
        $message = $this->builder->build([], $this->pagination(0, 1));

        $this->assertStringContainsString('Users', $message->getText()->getMessageText());
    }

    public function testBuildShowsUserButtonsWithRoleName(): void
    {
        $userRows = [
            $this->userRow(100, 'Alice', Role::Player),
            $this->userRow(200, 'Bob', Role::Admin),
        ];

        $message = $this->builder->build($userRows, $this->pagination(2, 1));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Alice — Player', $keyboard[0][0]['text']);
        $this->assertSame('Bob — Admin', $keyboard[1][0]['text']);
    }

    public function testBuildStylesUserButtonsByRole(): void
    {
        $userRows = [
            $this->userRow(100, 'Rita', Role::Root),
            $this->userRow(200, 'Adam', Role::Admin),
            $this->userRow(300, 'Pola', Role::Player),
        ];

        $message = $this->builder->build($userRows, $this->pagination(3, 1));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('primary', $keyboard[0][0]['style']);
        $this->assertSame('danger', $keyboard[1][0]['style']);
        $this->assertArrayNotHasKey('style', $keyboard[2][0]);
    }

    public function testBuildHasPaginationOnMultiplePages(): void
    {
        $userRows = [];
        for ($i = 1; $i <= self::PAGE_SIZE; $i++) {
            $userRows[] = $this->userRow($i, "User$i", Role::Player);
        }

        $message = $this->builder->build($userRows, $this->pagination(self::PAGE_SIZE + 2, 1));
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
        $message = $this->builder->build([$this->userRow(100, 'Alice', Role::Player)], $this->pagination(1, 1));

        $this->assertStringContainsString('Page 1 of 1', $message->getText()->getMessageText());
    }

    /** @return array<string, mixed> */
    private function userRow(int $telegramUserId, string $firstName, Role $role): array
    {
        return [
            'telegram_user_id' => $telegramUserId,
            'first_name' => $firstName,
            'last_name' => null,
            'username' => null,
            'role' => $role->value,
        ];
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
