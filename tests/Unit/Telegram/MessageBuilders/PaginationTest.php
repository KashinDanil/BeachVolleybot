<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\KeyboardPagination;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    // --- constructor: totalPages ---

    public function testTotalPagesRoundsUp(): void
    {
        $pagination = new KeyboardPagination(totalItems: 11, perPage: 5, page: 1);

        $this->assertSame(3, $pagination->getTotalPages());
    }

    public function testSinglePageWhenItemsEqualPerPage(): void
    {
        $pagination = new KeyboardPagination(totalItems: 5, perPage: 5, page: 1);

        $this->assertSame(1, $pagination->getTotalPages());
    }

    public function testZeroItemsYieldsOnePage(): void
    {
        $pagination = new KeyboardPagination(totalItems: 0, perPage: 5, page: 1);

        $this->assertSame(1, $pagination->getTotalPages());
        $this->assertSame(1, $pagination->getPage());
        $this->assertSame(0, $pagination->getOffset());
    }

    // --- constructor: page clamping ---

    public function testPageClampedToMinimumOne(): void
    {
        $pagination = new KeyboardPagination(totalItems: 10, perPage: 5, page: 0);

        $this->assertSame(1, $pagination->getPage());
    }

    public function testNegativePageClampedToOne(): void
    {
        $pagination = new KeyboardPagination(totalItems: 10, perPage: 5, page: -3);

        $this->assertSame(1, $pagination->getPage());
    }

    public function testPageClampedToMaximumTotalPages(): void
    {
        $pagination = new KeyboardPagination(totalItems: 10, perPage: 5, page: 99);

        $this->assertSame(2, $pagination->getPage());
    }

    // --- constructor: offset ---

    public function testOffsetCalculation(): void
    {
        $pagination = new KeyboardPagination(totalItems: 20, perPage: 5, page: 3);

        $this->assertSame(10, $pagination->getOffset());
    }

    public function testFirstPageOffsetIsZero(): void
    {
        $pagination = new KeyboardPagination(totalItems: 20, perPage: 5, page: 1);

        $this->assertSame(0, $pagination->getOffset());
    }

    // --- getPreviousPage / getNextPage ---

    public function testNoPagesOnSinglePage(): void
    {
        $pagination = new KeyboardPagination(totalItems: 3, perPage: 5, page: 1);

        $this->assertNull($pagination->getPreviousPage());
        $this->assertNull($pagination->getNextPage());
    }

    public function testNextPageOnlyOnFirstPage(): void
    {
        $pagination = new KeyboardPagination(totalItems: 15, perPage: 5, page: 1);

        $this->assertNull($pagination->getPreviousPage());
        $this->assertSame(2, $pagination->getNextPage());
    }

    public function testPreviousPageOnlyOnLastPage(): void
    {
        $pagination = new KeyboardPagination(totalItems: 15, perPage: 5, page: 3);

        $this->assertSame(2, $pagination->getPreviousPage());
        $this->assertNull($pagination->getNextPage());
    }

    public function testBothPagesOnMiddlePage(): void
    {
        $pagination = new KeyboardPagination(totalItems: 15, perPage: 5, page: 2);

        $this->assertSame(1, $pagination->getPreviousPage());
        $this->assertSame(3, $pagination->getNextPage());
    }
}
