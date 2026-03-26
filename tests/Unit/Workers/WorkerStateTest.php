<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Workers;

use BeachVolleybot\Workers\WorkerState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkerStateTest extends TestCase
{
    public function testIsRunningReturnsTrueOnlyForRunning(): void
    {
        $this->assertTrue(WorkerState::RUNNING->isRunning());
    }

    #[DataProvider('nonRunningStatesProvider')]
    public function testIsRunningReturnsFalseForNonRunningStates(WorkerState $state): void
    {
        $this->assertFalse($state->isRunning());
    }

    public static function nonRunningStatesProvider(): array
    {
        return [
            [WorkerState::STARTING],
            [WorkerState::STOP_REQUESTED],
            [WorkerState::STOPPING],
            [WorkerState::STOPPED],
        ];
    }

    public function testIsStopRequestedReturnsTrueOnlyForStopRequested(): void
    {
        $this->assertTrue(WorkerState::STOP_REQUESTED->isStopRequested());
    }

    #[DataProvider('nonStopRequestedStatesProvider')]
    public function testIsStopRequestedReturnsFalseForOtherStates(WorkerState $state): void
    {
        $this->assertFalse($state->isStopRequested());
    }

    public static function nonStopRequestedStatesProvider(): array
    {
        return [
            [WorkerState::STARTING],
            [WorkerState::RUNNING],
            [WorkerState::STOPPING],
            [WorkerState::STOPPED],
        ];
    }
}