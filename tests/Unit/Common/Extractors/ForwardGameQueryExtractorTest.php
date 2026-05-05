<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common\Extractors;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use PHPUnit\Framework\TestCase;

final class ForwardGameQueryExtractorTest extends TestCase
{
    public function testExtractsGameIdFromEnglishQuery(): void
    {
        $this->assertSame(123, ForwardGameQueryExtractor::extract('Forward game 123'));
    }

    public function testToleratesMultipleSpacesBetweenPrefixAndId(): void
    {
        $this->assertSame(9, ForwardGameQueryExtractor::extract('Forward game   9'));
    }

    public function testIsCaseInsensitive(): void
    {
        $this->assertSame(123, ForwardGameQueryExtractor::extract('forward GAME 123'));
    }

    public function testReturnsNullWhenIdIsMissing(): void
    {
        $this->assertNull(ForwardGameQueryExtractor::extract('Forward game'));
    }

    public function testReturnsNullWhenIdIsNotNumeric(): void
    {
        $this->assertNull(ForwardGameQueryExtractor::extract('Forward game abc'));
    }

    public function testReturnsNullWhenIdIsZero(): void
    {
        $this->assertNull(ForwardGameQueryExtractor::extract('Forward game 0'));
    }

    public function testReturnsNullForUnrelatedQuery(): void
    {
        $this->assertNull(ForwardGameQueryExtractor::extract('Saturday 18:00'));
    }

    public function testReturnsNullForEmptyQuery(): void
    {
        $this->assertNull(ForwardGameQueryExtractor::extract(''));
    }

    public function testReturnsNullForLocalizedPrefix(): void
    {
        $this->assertNull(ForwardGameQueryExtractor::extract('Переслать игру 7'));
    }

    public function testReturnsNullWhenLeadingWhitespacePresent(): void
    {
        $this->assertNull(ForwardGameQueryExtractor::extract('  Forward game 5'));
    }

    public function testReturnsNullWhenIdHasTrailingNonDigit(): void
    {
        $this->assertNull(ForwardGameQueryExtractor::extract('Forward game 12a'));
    }
}
