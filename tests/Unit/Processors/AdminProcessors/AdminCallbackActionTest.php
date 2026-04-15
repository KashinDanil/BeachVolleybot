<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\AdminAddNetProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminAddVolleyballProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\AdminGameDetailCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminGamesListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminPlayerSettingsProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminPlayersListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminRemoveLocationCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminRemoveNetProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminRemoveSlotProcessor;
use BeachVolleybot\Processors\AdminProcessors\AdminRemoveVolleyballProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogClearCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogFileActionsCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogGetCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogsListCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\LogTailCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\TelegramMessageSender;
use PHPUnit\Framework\TestCase;

final class AdminCallbackActionTest extends TestCase
{
    private TelegramMessageSender $sender;

    public function testResolvesAllActions(): void
    {
        $mapping = [
            [AdminCallbackAction::Settings, SettingsMenuCallbackProcessor::class],
            [AdminCallbackAction::Logs, LogsListCallbackProcessor::class],
            [AdminCallbackAction::LogFile, LogFileActionsCallbackProcessor::class],
            [AdminCallbackAction::LogGet, LogGetCallbackProcessor::class],
            [AdminCallbackAction::LogTail, LogTailCallbackProcessor::class],
            [AdminCallbackAction::LogClear, LogClearCallbackProcessor::class],
            [AdminCallbackAction::GamesList, AdminGamesListCallbackProcessor::class],
            [AdminCallbackAction::GameDetail, AdminGameDetailCallbackProcessor::class],
            [AdminCallbackAction::GamePlayers, AdminPlayersListCallbackProcessor::class],
            [AdminCallbackAction::PlayerSettings, AdminPlayerSettingsProcessor::class],
            [AdminCallbackAction::RemoveSlot, AdminRemoveSlotProcessor::class],
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

    protected function setUp(): void
    {
        $this->sender = $this->createStub(TelegramMessageSender::class);
    }
}
