<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

final readonly class KeyboardPagination
{
    public int $page;

    public int $totalPages;

    public int $offset;

    public function __construct(int $totalItems, int $perPage, int $page)
    {
        $this->totalPages = max(1, (int)ceil($totalItems / $perPage));
        $this->page = max(1, min($page, $this->totalPages));
        $this->offset = ($this->page - 1) * $perPage;
    }

    public function getPreviousPage(): ?int
    {
        if (1 < $this->page) {
            return $this->page - 1;
        }

        return null;
    }

    public function getNextPage(): ?int
    {
        if ($this->totalPages > $this->page) {
            return $this->page + 1;
        }

        return null;
    }
}
