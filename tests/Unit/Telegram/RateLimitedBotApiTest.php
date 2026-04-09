<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram;

use BeachVolleybot\Telegram\RateLimitedBotApi;
use PHPUnit\Framework\TestCase;
use TelegramBot\Api\HttpException;

final class RateLimitedBotApiTest extends TestCase
{
    public function testRetryOnRateLimitAndEventuallySucceed(): void
    {
        $bot = new RateLimitedBotApiStub(100);
        $bot->callResults = [
            new HttpException('Too Many Requests: retry after 1', 429, null, ['retry_after' => 1]),
            'success',
        ];

        $result = $bot->call('sendMessage', ['chat_id' => 1, 'text' => 'hi']);

        $this->assertSame('success', $result);
        $this->assertSame(2, $bot->parentCallCount);
    }

    public function testRethrowAfterMaxRetriesExhausted(): void
    {
        $bot = new RateLimitedBotApiStub(100);
        $bot->callResults = array_fill(0, 3, new HttpException('Too Many Requests: retry after 1', 429, null, ['retry_after' => 1]));

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(429);

        $bot->call('sendMessage');
    }

    public function testMaxRetriesUsesExactlyThreeAttempts(): void
    {
        $bot = new RateLimitedBotApiStub(100);
        $bot->callResults = array_fill(0, 2, new HttpException('Too Many Requests: retry after 1', 429, null, ['retry_after' => 1]));

        try {
            $bot->call('sendMessage');
        } catch (HttpException) {
        }

        $this->assertSame(2, $bot->parentCallCount);
    }

    public function testNonRateLimitExceptionIsNotRetried(): void
    {
        $bot = new RateLimitedBotApiStub(100);
        $bot->callResults = [
            new HttpException('Bad Request', 400),
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(400);

        $bot->call('sendMessage');
    }

    public function testNonRateLimitExceptionAttemptedOnlyOnce(): void
    {
        $bot = new RateLimitedBotApiStub(100);
        $bot->callResults = [
            new HttpException('Bad Request', 400),
        ];

        try {
            $bot->call('sendMessage');
        } catch (HttpException) {
        }

        $this->assertSame(1, $bot->parentCallCount);
    }

    public function testProactiveRateLimitingAddsDelay(): void
    {
        $bot = new RateLimitedBotApiStub(2); // 2 RPS = 500ms interval
        $bot->callResults = ['first', 'second'];

        $start = microtime(true);
        $bot->call('sendMessage');
        $bot->call('sendMessage');
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(0.4, $elapsed);
    }
}

/**
 * @internal
 */
class RateLimitedBotApiStub extends RateLimitedBotApi
{
    /** @var list<mixed|HttpException> */
    public array $callResults = [];

    public int $parentCallCount = 0;

    public function __construct(int $maxRequestsPerSecond)
    {
        parent::__construct('test_token', $maxRequestsPerSecond);
    }

    /** @noinspection MagicMethodsValidityInspection */
    public function __destruct()
    {
    }

    protected function parentCall($method, ?array $data, $timeout): mixed
    {
        $this->parentCallCount++;

        $result = array_shift($this->callResults);

        if ($result instanceof HttpException) {
            throw $result;
        }

        return $result;
    }

    protected function retrySleep(int $seconds): void
    {
    }
}
