<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\Admin\UserRoleDetailMessageBuilder;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\Role;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class UserRoleDetailMessageBuilderTest extends TestCase
{
    use CreatesUserRecords;

    private UserRoleDetailMessageBuilder $builder;

    public function testShowsRoleNameAsText(): void
    {
        $message = $this->builder->buildUserDetail($this->userRecord(100, 'Alice', role: Role::Admin));

        $this->assertStringContainsString('Role: Admin', $message->getText()->getMessageText());
    }

    public function testShowsTelegramId(): void
    {
        $message = $this->builder->buildUserDetail($this->userRecord(100, 'Alice', role: Role::Player));

        $this->assertStringContainsString('Telegram ID: `100`', $message->getText()->getMessageText());
    }

    public function testShowsLanguageCodeAfterTheRole(): void
    {
        $message = $this->builder->buildUserDetail($this->userRecord(100, 'Alice', languageCode: 'ru'));

        $this->assertStringContainsString("Role: Player\nLanguage: ru\n", $message->getText()->getMessageText());
    }

    public function testShowsADashForAnUnknownLanguage(): void
    {
        $message = $this->builder->buildUserDetail($this->userRecord(100, 'Alice'));

        $this->assertStringContainsString('Language: —', $message->getText()->getMessageText());
    }

    public function testShowsStoredNotificationsAsOptedInAfterTheLanguage(): void
    {
        $message = $this->builder->buildUserDetail($this->userRecord(100, 'Alice', notifications: new NotificationSettings()));

        $this->assertStringContainsString("Language: —\nNotifications: Opted in\n", $message->getText()->getMessageText());
    }

    public function testShowsADashForUnsetNotifications(): void
    {
        $message = $this->builder->buildUserDetail($this->userRecord(100, 'Alice', notifications: null));

        $this->assertStringContainsString('Notifications: —', $message->getText()->getMessageText());
    }

    public function testShowsCreatedAndUpdatedInUtc(): void
    {
        $madrid = new DateTimeZone('Europe/Madrid');
        $user = $this->userRecord(
            100,
            'Alice',
            createdAt: new DateTimeImmutable('2026-03-05 18:30:00', $madrid),
            updatedAt: new DateTimeImmutable('2026-07-20 09:05:00', $madrid),
        );

        $text = $this->builder->buildUserDetail($user)->getText()->getMessageText();

        $this->assertStringEndsWith(
            "Created: 2026\\-03\\-05 17:30:00 UTC\nUpdated: 2026\\-07\\-20 07:05:00 UTC",
            $text,
        );
    }

    public function testPlayerHasPromoteButton(): void
    {
        $message = $this->builder->buildUserDetail($this->userRecord(100, 'Alice', role: Role::Player));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Promote to Admin', $keyboard[0][0]['text']);
        $this->assertSame('danger', $keyboard[0][0]['style']);
        $this->assertBackRowLast($keyboard);
    }

    public function testAdminHasDemoteButton(): void
    {
        $message = $this->builder->buildUserDetail($this->userRecord(100, 'Alice', role: Role::Admin));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('Demote to Player', $keyboard[0][0]['text']);
        $this->assertArrayNotHasKey('style', $keyboard[0][0]);
        $this->assertBackRowLast($keyboard);
    }

    public function testRootHasNoActionButton(): void
    {
        $message = $this->builder->buildUserDetail($this->userRecord(100, 'Alice', role: Role::Root));
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(1, $keyboard);
        $this->assertSame("\u{21A9} Back", $keyboard[0][0]['text']);
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
