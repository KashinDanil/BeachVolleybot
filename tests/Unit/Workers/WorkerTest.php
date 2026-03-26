<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Workers;

use BeachVolleybot\Workers\Worker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @requires extension pcntl
 * @requires extension posix
 */
final class WorkerTest extends TestCase
{
    public function testTickIsCalledDuringRun(): void
    {
        $tickCount = 0;
        $worker = $this->makeWorker(function () use (&$tickCount): void {
            $tickCount++;
            if (3 === $tickCount) {
                posix_kill(posix_getpid(), SIGTERM);
            }
        });

        $worker->run();

        $this->assertSame(3, $tickCount);
    }

    public function testOnStoppingIsCalledOnShutdown(): void
    {
        $onStoppingCalled = false;

        $worker = $this->makeWorker(
            fn() => posix_kill(posix_getpid(), SIGTERM),
            function () use (&$onStoppingCalled): void {
                $onStoppingCalled = true;
            },
        );

        $worker->run();

        $this->assertTrue($onStoppingCalled);
    }

    public function testTickExceptionDoesNotCrashWorker(): void
    {
        $tickCount = 0;
        $worker = $this->makeWorker(function () use (&$tickCount): void {
            $tickCount++;
            if (1 === $tickCount) {
                throw new RuntimeException('tick error');
            }

            posix_kill(posix_getpid(), SIGTERM);
        });

        $worker->run();

        $this->assertSame(2, $tickCount);
    }

    public function testStopSignalReceivedIsFalseBeforeSignal(): void
    {
        $stopSignalReceived = null;

        $worker = $this->makeWorker(function (\Closure $checkStop) use (&$stopSignalReceived): void {
            $stopSignalReceived = $checkStop(); // capture before signal
            posix_kill(posix_getpid(), SIGTERM);
        });

        $worker->run();

        $this->assertFalse($stopSignalReceived);
    }

    public function testStopSignalReceivedIsTrueAfterSignal(): void
    {
        $stopSignalReceived = null;

        $worker = $this->makeWorker(function (\Closure $checkStop) use (&$stopSignalReceived): void {
            posix_kill(posix_getpid(), SIGTERM);
            $stopSignalReceived = $checkStop(); // capture after signal
        });

        $worker->run();

        $this->assertTrue($stopSignalReceived);
    }

    // -------------------------------------------------------------------------

    /**
     * Creates a Worker whose tick receives a $checkStop closure (wrapping the
     * protected stopSignalReceived()) so tests can inspect stop state mid-tick.
     */
    private function makeWorker(callable $tick, ?callable $onStopping = null): Worker
    {
        return new class(
            \Closure::fromCallable($tick),
            null !== $onStopping ? \Closure::fromCallable($onStopping) : null,
        ) extends Worker {
            public function __construct(
                private readonly \Closure $tickFn,
                private readonly ?\Closure $onStoppingFn,
            ) {
                parent::__construct();
            }

            protected function tick(): void
            {
                // Pass a $checkStop closure so callers can probe stopSignalReceived()
                // without needing direct access to the protected method.
                ($this->tickFn)(fn() => $this->stopSignalReceived());
            }

            protected function onStopping(): void
            {
                ($this->onStoppingFn ?? fn() => null)();
            }

            protected function getTickIntervalMs(): int
            {
                return 0;
            }
        };
    }
}