<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\AdminProcessors;

use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\RootDemoteUserProcessor;
use BeachVolleybot\Processors\AdminProcessors\RootPromoteUserProcessor;
use BeachVolleybot\Processors\AdminProcessors\RootUserRoleDetailProcessor;
use BeachVolleybot\Processors\AdminProcessors\RootUserRoleListProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\Role;

final class UserRoleProcessorsTest extends ProcessorTestCase
{
    // --- UserRoleListProcessor ---

    public function testUsersListEditsMessage(): void
    {
        $this->seedRoot();
        $this->createUser(300, 'Alice');

        $callbackData = AdminCallbackData::create(AdminCallbackAction::UsersList)->withPage(1);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootUserRoleListProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- UserRoleDetailProcessor ---

    public function testUserDetailEditsMessage(): void
    {
        $this->createUser(300, 'Alice', role: Role::Player->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::UserDetail)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootUserRoleDetailProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    public function testUserDetailShowsUserNotFound(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::UserDetail)->withUserId(99999);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootUserRoleDetailProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertMessageEdited();
    }

    // --- PromoteUserProcessor ---

    public function testPromoteChangesRoleToAdmin(): void
    {
        $this->createUser(300, 'Alice', role: Role::Player->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::PromoteUser)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootPromoteUserProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertSame(Role::Admin->value, new UserRepository($this->db)->findRoleById(300));
        $this->assertMessageEdited();
        $this->assertAnsweredWith('Promoted to Admin');
    }

    public function testPromoteRootIsBlocked(): void
    {
        $this->createUser(300, 'Alice', role: Role::Root->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::PromoteUser)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootPromoteUserProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertSame(Role::Root->value, new UserRepository($this->db)->findRoleById(300));
        $this->assertAnsweredWith('Cannot change Root');
    }

    public function testPromoteAnswersUserNotFound(): void
    {
        $callbackData = AdminCallbackData::create(AdminCallbackAction::PromoteUser)->withUserId(99999);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootPromoteUserProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertNull(new UserRepository($this->db)->findRoleById(99999));
        $this->assertAnsweredWith('User not found');
    }

    // --- DemoteUserProcessor ---

    public function testDemoteChangesRoleToPlayer(): void
    {
        $this->createUser(300, 'Alice', role: Role::Admin->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::DemoteUser)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootDemoteUserProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertSame(Role::Player->value, new UserRepository($this->db)->findRoleById(300));
        $this->assertMessageEdited();
        $this->assertAnsweredWith('Demoted to Player');
    }

    public function testDemoteRootIsBlocked(): void
    {
        $this->createUser(300, 'Alice', role: Role::Root->value);

        $callbackData = AdminCallbackData::create(AdminCallbackAction::DemoteUser)->withUserId(300);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        new RootDemoteUserProcessor($this->telegramSender, $callbackData)->process($update);

        $this->assertSame(Role::Root->value, new UserRepository($this->db)->findRoleById(300));
        $this->assertAnsweredWith('Cannot change Root');
    }
}
