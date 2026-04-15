<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\FileSize;
use PHPUnit\Framework\TestCase;

final class FileSizeTest extends TestCase
{
    public function testZeroBytesFormatsAsBytes(): void
    {
        $this->assertSame('0 B', FileSize::format(0));
    }

    public function testSmallBytesValueFormatsAsBytes(): void
    {
        $this->assertSame('512 B', FileSize::format(512));
    }

    public function testMaxBytesBeforeKilobyte(): void
    {
        $this->assertSame('1023 B', FileSize::format(1023));
    }

    public function testExactlyOneKilobyte(): void
    {
        $this->assertSame('1 KB', FileSize::format(1024));
    }

    public function testKilobytesWithDecimal(): void
    {
        $this->assertSame('1.5 KB', FileSize::format(1536));
    }

    public function testLargeKilobytesValue(): void
    {
        $this->assertSame('500 KB', FileSize::format(512000));
    }

    public function testMaxKilobytesBeforeMegabyte(): void
    {
        $this->assertSame('1024 KB', FileSize::format(1048575));
    }

    public function testExactlyOneMegabyte(): void
    {
        $this->assertSame('1 MB', FileSize::format(1048576));
    }

    public function testMegabytesWithDecimal(): void
    {
        $this->assertSame('2.5 MB', FileSize::format(2621440));
    }

    public function testLargeMegabytesValue(): void
    {
        $this->assertSame('100 MB', FileSize::format(104857600));
    }
}