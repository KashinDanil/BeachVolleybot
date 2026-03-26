<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Common\Logger;
use ReflectionClass;
use Throwable;

abstract class Worker
{
    private const int DEFAULT_TICK_INTERVAL_MS = 100;

    protected bool $verbose = false;

    private WorkerState $state = WorkerState::STARTING;

    public function __construct(bool $verbose = false)
    {
        $this->verbose = $verbose;

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn() => $this->requestStop());
        pcntl_signal(SIGINT, fn() => $this->requestStop());
    }

    public function run(): void
    {
        $this->state = WorkerState::RUNNING;

        while ($this->state->isRunning()) {
            try {
                $this->tick();
            } catch (Throwable $e) {
                $this->handleTickError($e);
            }

            if ($this->state->isRunning()) {
                $this->sleepAfterTick();
            }
        }

        $this->state = WorkerState::STOPPING;
        $this->onStopping();
        $this->state = WorkerState::STOPPED;
    }

    abstract protected function tick(): void;

    protected function onStopping(): void
    {
    }

    protected function stopSignalReceived(): bool //Allow use in inherited classes
    {
        return $this->state->isStopRequested();
    }

    protected function handleTickError(Throwable $e): void
    {
        $workerName = new ReflectionClass($this)->getShortName();

        Logger::logApp(sprintf('[%s] Worker tick failed: %s in %s:%d', $workerName, $e->getMessage(), $e->getFile(), $e->getLine()));
        $this->verboseEcho(sprintf("\nERROR: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine()));
    }

    protected function getTickIntervalMs(): int
    {
        return self::DEFAULT_TICK_INTERVAL_MS;
    }

    protected function verboseEcho(string $text): void
    {
        if ($this->verbose) {
            echo $text;
        }
    }

    private function sleepAfterTick(): void
    {
        $this->verboseEcho('.');
        usleep($this->getTickIntervalMs() * 1_000);
    }

    private function requestStop(): void
    {
        $this->state = WorkerState::STOP_REQUESTED;
    }
}