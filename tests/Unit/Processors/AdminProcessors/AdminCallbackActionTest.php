<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminAddNetProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminAddSlotProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminAddVolleyballProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminRemoveLocationCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminRemoveNetProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminRemoveSlotProcessor;
use BeachVolleybot\Processors\AdminProcessors\Equipment\AdminRemoveVolleyballProcessor;
use BeachVolleybot\Processors\AdminProcessors\Games\AdminGameDetailCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Games\AdminGamesListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Games\Users\AdminUserSettingsProcessor;
use BeachVolleybot\Processors\AdminProcessors\Games\Users\AdminUsersListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogClearCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogFileActionsCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogGetCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogsListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogTailCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootDemoteUserProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootPromoteUserProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootUserRoleDetailProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\UserRole\RootUserRoleListProcessor;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\Role;
use PHPUnit\Framework\TestCase;

final class AdminCallbackActionTest extends TestCase
{
    private TelegramMessageSender $sender;

    public function testResolvesAllActions(): void
    {
        $mapping = [
            [AdminCallbackAction::Settings, SettingsMenuCallbackProcessor::class],
            [AdminCallbackAction::Logs, RootLogsListCallbackProcessor::class],
            [AdminCallbackAction::LogFile, RootLogFileActionsCallbackProcessor::class],
            [AdminCallbackAction::LogGet, RootLogGetCallbackProcessor::class],
            [AdminCallbackAction::LogTail, RootLogTailCallbackProcessor::class],
            [AdminCallbackAction::LogClear, RootLogClearCallbackProcessor::class],
            [AdminCallbackAction::GamesList, AdminGamesListCallbackProcessor::class],
            [AdminCallbackAction::GameDetail, AdminGameDetailCallbackProcessor::class],
            [AdminCallbackAction::GameUsers, AdminUsersListCallbackProcessor::class],
            [AdminCallbackAction::UserSettings, AdminUserSettingsProcessor::class],
            [AdminCallbackAction::UsersList, RootUserRoleListProcessor::class],
            [AdminCallbackAction::UserDetail, RootUserRoleDetailProcessor::class],
            [AdminCallbackAction::PromoteUser, RootPromoteUserProcessor::class],
            [AdminCallbackAction::DemoteUser, RootDemoteUserProcessor::class],
            [AdminCallbackAction::RemoveSlot, AdminRemoveSlotProcessor::class],
            [AdminCallbackAction::AddSlot, AdminAddSlotProcessor::class],
            [AdminCallbackAction::RemoveLocation, AdminRemoveLocationCallbackProcessor::class],
            [AdminCallbackAction::AddNet, AdminAddNetProcessor::class],
            [AdminCallbackAction::RemoveNet, AdminRemoveNetProcessor::class],
            [AdminCallbackAction::AddVolleyball, AdminAddVolleyballProcessor::class],
            [AdminCallbackAction::RemoveVolleyball, AdminRemoveVolleyballProcessor::class],
        ];

        foreach ($mapping as [$action, $expectedClass]) {
            $processor = $action->resolveProcessor($this->sender, AdminCallbackData::create($action));
            $this->assertInstanceOf($expectedClass, $processor, "Failed for action '$action->value'");
        }
    }

    public function testLogActionsRequireRoot(): void
    {
        $logActions = [
            AdminCallbackAction::Logs,
            AdminCallbackAction::LogFile,
            AdminCallbackAction::LogGet,
            AdminCallbackAction::LogTail,
            AdminCallbackAction::LogClear,
        ];

        foreach ($logActions as $action) {
            $this->assertSame(Role::Root, $action->requiredRole(), "Failed for action '$action->value'");
        }
    }

    public function testUserManagementActionsRequireRoot(): void
    {
        $userManagementActions = [
            AdminCallbackAction::UsersList,
            AdminCallbackAction::UserDetail,
            AdminCallbackAction::PromoteUser,
            AdminCallbackAction::DemoteUser,
        ];

        foreach ($userManagementActions as $action) {
            $this->assertSame(Role::Root, $action->requiredRole(), "Failed for action '$action->value'");
        }
    }

    public function testNonLogActionsRequireAdmin(): void
    {
        $adminActions = [
            AdminCallbackAction::Settings,
            AdminCallbackAction::GamesList,
            AdminCallbackAction::GameDetail,
            AdminCallbackAction::GameUsers,
            AdminCallbackAction::UserSettings,
            AdminCallbackAction::RemoveSlot,
            AdminCallbackAction::AddSlot,
            AdminCallbackAction::RemoveLocation,
            AdminCallbackAction::AddNet,
            AdminCallbackAction::RemoveNet,
            AdminCallbackAction::AddVolleyball,
            AdminCallbackAction::RemoveVolleyball,
        ];

        foreach ($adminActions as $action) {
            $this->assertSame(Role::Admin, $action->requiredRole(), "Failed for action '$action->value'");
        }
    }

    protected function setUp(): void
    {
        $this->sender = $this->createStub(TelegramMessageSender::class);
    }
}
