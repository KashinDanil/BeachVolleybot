<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Common\Logger;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\HttpException;

class RateLimitedBotApi extends BotApi
{
    private const int MAX_ATTEMPTS = 3;

    private readonly float $minimumRequestInterval;

    private float $lastRequestTimestamp = 0.0;

    public function __construct(string $token, int $maxRequestsPerSecond)
    {
        parent::__construct($token);

        $this->minimumRequestInterval = 1.0 / $maxRequestsPerSecond;
    }

    public function call($method, ?array $data = null, $timeout = 10)
    {
        $attempt = 0;

        while (true) {
            $this->waitForRateLimit();
            $this->lastRequestTimestamp = microtime(true);

            try {
                return $this->parentCall($method, $data, $timeout);
            } catch (HttpException $exception) {
                if (self::MAX_ATTEMPTS <= ++$attempt || 429 !== $exception->getCode()) {
                    throw $exception;
                }

                $retryAfter = (int)($exception->getParameters()['retry_after'] ?? 1);

                Logger::logApp(
                    sprintf('Rate limited on "%s", attempt %d/%d after %ds', $method, $attempt + 1, self::MAX_ATTEMPTS, $retryAfter)
                );

                $this->retrySleep($retryAfter);
            }
        }
    }

    protected function parentCall($method, ?array $data, $timeout): mixed
    {
        return parent::call($method, $data, $timeout);
    }

    protected function retrySleep(int $seconds): void
    {
        sleep($seconds);
    }

    private function waitForRateLimit(): void
    {
        $elapsed = microtime(true) - $this->lastRequestTimestamp;

        if ($elapsed < $this->minimumRequestInterval) {
            usleep((int)(($this->minimumRequestInterval - $elapsed) * 1_000_000));
        }
    }
}
