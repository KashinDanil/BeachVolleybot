<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Processors\AdminProcessors;

use BeachVolleybot\Processors\AdminProcessors\RoleGateProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\User\Role;
use PHPUnit\Framework\TestCase;

final class RoleGateProcessorTest extends TestCase
{
    use CreatesUserRecords;

    private TelegramMessageSender $telegramSender;
    private AbstractActionProcessor $allowedProcessor;
    private AbstractActionProcessor $deniedProcessor;

    protected function setUp(): void
    {
        $this->telegramSender = $this->createStub(TelegramMessageSender::class);
        $this->allowedProcessor = $this->spyProcessor();
        $this->deniedProcessor = $this->spyProcessor();
    }

    public function testRunsTheAllowedProcessorForTheRequiredRole(): void
    {
        $this->process(Role::Admin, Role::Admin, $this->deniedProcessor);

        $this->assertTrue($this->allowedProcessor->processed);
        $this->assertFalse($this->deniedProcessor->processed);
    }

    public function testRunsTheAllowedProcessorForAHigherRole(): void
    {
        $this->process(Role::Root, Role::Admin, $this->deniedProcessor);

        $this->assertTrue($this->allowedProcessor->processed);
    }

    public function testRunsTheDeniedProcessorBelowTheRequiredRole(): void
    {
        $this->process(Role::Admin, Role::Root, $this->deniedProcessor);

        $this->assertFalse($this->allowedProcessor->processed);
        $this->assertTrue($this->deniedProcessor->processed);
    }

    public function testDoesNothingBelowTheRequiredRoleWithoutADeniedProcessor(): void
    {
        $this->process(Role::Player, Role::Admin, null);

        $this->assertFalse($this->allowedProcessor->processed);
    }

    public function testHandsTheSameUpdateToTheChosenProcessor(): void
    {
        $update = TelegramUpdate::fromArray(['update_id' => 7]);

        new RoleGateProcessor($this->telegramSender, $this->userRecord(role: Role::Admin), Role::Admin, $this->allowedProcessor)
            ->process($update);

        $this->assertSame($update, $this->allowedProcessor->processedUpdate);
    }

    private function process(Role $senderRole, Role $requiredRole, ?AbstractActionProcessor $deniedProcessor): void
    {
        new RoleGateProcessor(
            $this->telegramSender,
            $this->userRecord(role: $senderRole),
            $requiredRole,
            $this->allowedProcessor,
            $deniedProcessor,
        )->process(TelegramUpdate::fromArray(['update_id' => 7]));
    }

    private function spyProcessor(): AbstractActionProcessor
    {
        return new class($this->telegramSender) extends AbstractActionProcessor {
            public bool $processed = false;
            public ?TelegramUpdate $processedUpdate = null;

            public function process(TelegramUpdate $update): void
            {
                $this->processed = true;
                $this->processedUpdate = $update;
            }
        };
    }
}
