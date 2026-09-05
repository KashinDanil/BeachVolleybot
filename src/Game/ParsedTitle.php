<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

/**
 * The kickoff and venue a free-text title carries, resolved once at write time so the
 * games row can hold them as columns.
 */
final readonly class ParsedTitle
{
    private const string KICKOFF_FORMAT = 'Y-m-d H:i:s';

    private function __construct(
        public ?string $kickoffAt,
        public ?string $venueName,
    ) {
    }

    public static function resolve(string $title, DateTimeImmutable $createdAt): self
    {
        return new self(
            GameDateTimeResolver::resolve($title, $createdAt)?->format(self::KICKOFF_FORMAT),
            KnownVenues::findInTitle($title)?->name,
        );
    }
}
