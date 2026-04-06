<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Errors;

use BeachVolleybot\Errors\MultiError;
use BeachVolleybot\Errors\ValidationError;
use PHPUnit\Framework\TestCase;

final class MultiErrorTest extends TestCase
{
    public function testGetMessageJoinsErrorMessages(): void
    {
        $multi = new MultiError([
            new ValidationError('First error'),
            new ValidationError('Second error'),
        ]);

        $this->assertSame('First error; Second error', $multi->getMessage());
    }

    public function testGetMessageWithSingleError(): void
    {
        $multi = new MultiError([new ValidationError('Only error')]);

        $this->assertSame('Only error', $multi->getMessage());
    }

    public function testGetDataMergesAllErrorData(): void
    {
        $multi = new MultiError([
            new ValidationError('a', ['key1' => 'val1']),
            new ValidationError('b', ['key2' => 'val2']),
        ]);

        $this->assertSame(['key1' => 'val1', 'key2' => 'val2'], $multi->getData());
    }

    public function testGetDataWithNoData(): void
    {
        $multi = new MultiError([
            new ValidationError('a'),
            new ValidationError('b'),
        ]);

        $this->assertSame([], $multi->getData());
    }

    public function testGetErrorsReturnsOriginalErrors(): void
    {
        $e1 = new ValidationError('a');
        $e2 = new ValidationError('b');
        $multi = new MultiError([$e1, $e2]);

        $this->assertSame([$e1, $e2], $multi->getErrors());
    }
}