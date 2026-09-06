<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

/**
 * The kickoff and venue a free-text title carries, read out once at write time so the
 * game row can hold them as columns.
 */
final readonly class ParsedTitle
{
    private function __construct(
        public DateTimeImmutable $kickoffAt,
        public ?string $venueName,
    ) {
    }

    public static function parse(string $title, DateTimeImmutable $createdAt): self
    {
        return new self(
            GameDateTimeResolver::resolveOrFail($title, $createdAt),
            KnownVenues::findInTitle($title)?->name,
        );
    }
}
