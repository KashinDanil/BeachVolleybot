<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Common\Logger;
use ReflectionClass;
use Throwable;

abstract class Worker
{
    private const int DEFAULT_TICK_INTERVAL_MS = 100;

    public bool $verbose = false {
        set {
            $this->verbose = $value;
        }
    }

    public function __construct(bool $verbose = false)
    {
        $this->verbose = $verbose;
    }

    abstract protected function tick(): void;

    protected function verboseEcho(string $text): void
    {
        if ($this->verbose) {
            echo $text;
        }
    }

    protected function getTickIntervalMs(): int
    {
        return self::DEFAULT_TICK_INTERVAL_MS;
    }

    public function run(): void
    {
        while (true) {
            try {
                $this->tick();
                $this->verboseEcho('.');
            } catch (Throwable $e) {
                Logger::logApp(sprintf(
                    '[%s] Worker tick failed: %s in %s:%d',
                    new ReflectionClass($this)->getShortName(),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                ));
                $this->verboseEcho(sprintf(
                    "\nERROR: %s in %s:%d\n",
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                ));
            }

            usleep($this->getTickIntervalMs() * 1_000);
        }
    }
}