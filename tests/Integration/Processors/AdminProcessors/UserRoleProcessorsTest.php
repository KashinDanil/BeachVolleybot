<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootDemoteUserProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootPromoteUserProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootUserRoleDetailProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootUserRoleListProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserManager;

final class UserRoleProcessorsTest extends ProcessorTestCase
{
    // --- UserRoleListProcessor ---

    public function testUsersListEditsMessage(): void
    {
        $this->seedRoot();
        $this->createUser(300, 'Alice');

        $callbackData = AdminCallbackData::create(AdminCallbackAction::UsersList)->withPage(1);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootUserRoleListProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertMessageEdited();
    }

    // --- UserRoleDetailProcessor ---

    public function testUserDetailEditsMessage(): void
    {
        $this->createUser(300, 'Alice', role: Role::Player->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::UserDetail)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootUserRoleDetailProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertMessageEdited();
    }

    public function testUserDetailShowsTheStoredLanguageOptInAndTimestamps(): void
    {
        $this->createUser(300, 'Alice', languageCode: 'es');
        $this->db->update(
            'users',
            ['created_at' => '2026-03-05 17:30:00', 'updated_at' => '2026-07-20 07:05:00'],
            ['telegram_user_id' => 300],
        );

        $callbackData = AdminCallbackData::create(AdminCallbackAction::UserDetail)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootUserRoleDetailProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertStringEndsWith(
            "Language: es\nNotifications: —\nCreated: 2026-03-05 17:30:00 UTC\nUpdated: 2026-07-20 07:05:00 UTC",
            $this->editedText(),
        );
    }

    public function testUserDetailShowsUserNotFound(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::UserDetail)->withUserId(99999);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootUserRoleDetailProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertMessageEdited();
    }

    // --- PromoteUserProcessor ---

    public function testPromoteChangesRoleToAdmin(): void
    {
        $this->createUser(300, 'Alice', role: Role::Player->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::PromoteUser)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootPromoteUserProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertSame(Role::Admin, new UserManager()->findUserRecordById(300)?->role);
        $this->assertMessageEdited();
        $this->assertAnsweredWith('Promoted to Admin');
    }

    public function testPromoteRootIsBlocked(): void
    {
        $this->createUser(300, 'Alice', role: Role::Root->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::PromoteUser)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootPromoteUserProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertSame(Role::Root, new UserManager()->findUserRecordById(300)?->role);
        $this->assertAnsweredWith('Cannot change Root');
    }

    public function testPromoteAnswersUserNotFound(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::PromoteUser)->withUserId(99999);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootPromoteUserProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertNull(new UserManager()->findUserRecordById(99999));
        $this->assertAnsweredWith('User not found');
    }

    // --- DemoteUserProcessor ---

    public function testDemoteChangesRoleToPlayer(): void
    {
        $this->createUser(300, 'Alice', role: Role::Admin->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::DemoteUser)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootDemoteUserProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertSame(Role::Player, new UserManager()->findUserRecordById(300)?->role);
        $this->assertMessageEdited();
        $this->assertAnsweredWith('Demoted to Player');
    }

    public function testDemoteRootIsBlocked(): void
    {
        $this->createUser(300, 'Alice', role: Role::Root->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::DemoteUser)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootDemoteUserProcessor($this->telegramSender, $callbackData, $this->ensureSender($update))->process($update);

        $this->assertSame(Role::Root, new UserManager()->findUserRecordById(300)?->role);
        $this->assertAnsweredWith('Cannot change Root');
    }
}
