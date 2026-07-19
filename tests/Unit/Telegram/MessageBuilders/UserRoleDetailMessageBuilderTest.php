<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\UserRoleDetailMessageBuilder;
use BeachVolleybot\User\Role;
use PHPUnit\Framework\TestCase;

final class UserRoleDetailMessageBuilderTest extends TestCase
{
    private UserRoleDetailMessageBuilder $builder;

    public function testShowsRoleNameAsText(): void
    {
        $message = $this->builder->buildUserDetail($this->userRow(100, 'Alice', Role::Admin));

        $this->assertStringContainsString('Role: *Admin*', $message->getText()->getMessageText());
    }

    public function testShowsTelegramId(): void
    {
        $message = $this->builder->buildUserDetail($this->userRow(100, 'Alice', Role::Player));

        $this->assertStringContainsString('Telegram ID: `100`', $message->getText()->getMessageText());
    }

    public function testPlayerHasPromoteButton(): void
    {
        $message = $this->builder->buildUserDetail($this->userRow(100, 'Alice', Role::Player));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Promote to Admin', $keyboard[0][0]['text']);
        $this->assertSame('danger', $keyboard[0][0]['style']);
        $this->assertBackRowLast($keyboard);
    }

    public function testAdminHasDemoteButton(): void
    {
        $message = $this->builder->buildUserDetail($this->userRow(100, 'Alice', Role::Admin));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Demote to Player', $keyboard[0][0]['text']);
        $this->assertArrayNotHasKey('style', $keyboard[0][0]);
        $this->assertBackRowLast($keyboard);
    }

    public function testRootHasNoActionButton(): void
    {
        $message = $this->builder->buildUserDetail($this->userRow(100, 'Alice', Role::Root));
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(1, $keyboard);
        $this->assertSame("\u{21A9} Back", $keyboard[0][0]['text']);
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

    private function assertBackRowLast(array $keyboard): void
    {
        $lastRow = end($keyboard);
        $this->assertSame("\u{21A9} Back", $lastRow[0]['text']);
    }

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    protected function setUp(): void
    {
        $this->builder = new UserRoleDetailMessageBuilder();
    }
}
