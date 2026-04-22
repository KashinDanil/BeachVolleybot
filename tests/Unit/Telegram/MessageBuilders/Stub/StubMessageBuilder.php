<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Stub;

use BeachVolleybot\Telegram\MessageBuilders\AbstractMessageBuilder;

/**
 * @method string greet(string $name)
 * @method int    sum(int $a, int $b)
 */
final class StubMessageBuilder extends AbstractMessageBuilder
{
    protected function defaultGreet(string $name): string
    {
        return "Hello, $name";
    }

    protected function defaultSum(int $a, int $b): int
    {
        return $a + $b;
    }
}
