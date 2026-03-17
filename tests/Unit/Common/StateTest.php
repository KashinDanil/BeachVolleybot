<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\State;
use BeachVolleybot\Errors\ErrorInterface;
use PHPUnit\Framework\TestCase;

final class StateTest extends TestCase
{
    public function testSuccessIsSuccess(): void
    {
        $this->assertTrue(State::success()->isSuccess());
    }

    public function testErrorIsNotSuccess(): void
    {
        $error = $this->createStub(ErrorInterface::class);

        $this->assertFalse(State::error($error)->isSuccess());
    }

    public function testGetErrorReturnsInjectedError(): void
    {
        $error = $this->createStub(ErrorInterface::class);

        $this->assertSame($error, State::error($error)->getError());
    }
}