<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BadMethodCallException;
use BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Stub\StubMessageBuilder;
use PHPUnit\Framework\TestCase;

final class AbstractMessageBuilderTest extends TestCase
{
    private StubMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new StubMessageBuilder();
    }

    // --- __call fallback behavior (regression guard for the resolveDefault refactor) ---

    public function testCallDelegatesToDefaultWhenNoOverride(): void
    {
        $this->assertSame('Hello, Alice', $this->builder->greet('Alice'));
    }

    public function testCallInvokesOverrideWhenRegistered(): void
    {
        $this->builder->override('greet', static fn(string $name): string => "Hi $name");

        $this->assertSame('Hi Alice', $this->builder->greet('Alice'));
    }

    public function testCallThrowsOnUnknownMethod(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->builder->unknownMethod();
    }

    public function testCallForwardsMultipleArguments(): void
    {
        $this->assertSame(7, $this->builder->sum(3, 4));
    }

    // --- getEffective ---

    public function testGetEffectiveWithoutOverrideReturnsClosureThatInvokesDefault(): void
    {
        $effective = $this->builder->getEffective('greet');

        $this->assertSame('Hello, Bob', $effective('Bob'));
    }

    public function testGetEffectiveReturnsInstalledOverrideByIdentity(): void
    {
        $override = static fn(string $name): string => "Hey $name";
        $this->builder->override('greet', $override);

        $this->assertSame($override, $this->builder->getEffective('greet'));
    }

    public function testGetEffectiveThrowsOnUnknownMethod(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->builder->getEffective('unknownMethod');
    }

    // --- Composability: the whole point of getEffective ---

    public function testOverrideCanDecoratePreviousEffective(): void
    {
        $previous = $this->builder->getEffective('greet');
        $this->builder->override('greet', static fn(string $name): string => $previous($name) . '!');

        $this->assertSame('Hello, Alice!', $this->builder->greet('Alice'));
    }

    public function testTwoSuccessiveOverridesComposeInRegistrationOrder(): void
    {
        $firstPrevious = $this->builder->getEffective('greet');
        $this->builder->override('greet', static fn(string $name): string => $firstPrevious($name) . ' [1st]');

        $secondPrevious = $this->builder->getEffective('greet');
        $this->builder->override('greet', static fn(string $name): string => $secondPrevious($name) . ' [2nd]');

        $this->assertSame('Hello, Alice [1st] [2nd]', $this->builder->greet('Alice'));
    }
}
