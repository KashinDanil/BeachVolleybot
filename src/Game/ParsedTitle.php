<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\PlayersPerNetExtractor;
use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

/**
 * The kickoff, venue and players-per-net limit a free-text title carries, read out once at
 * write time so the game row can store them — kickoff and venue as columns, the limit folded
 * into settings_json.
 */
final readonly class ParsedTitle
{
    private function __construct(
        public DateTimeImmutable $kickoffAt,
        public ?string $venueName,
        public ?int $playersPerNet,
    ) {
    }

    public static function parse(string $title, DateTimeImmutable $createdAt): self
    {
        return new self(
            GameDateTimeResolver::resolveOrFail($title, $createdAt),
            KnownVenues::findInTitle($title)?->name,
            PlayersPerNetExtractor::resolvePlayersPerNet($title),
        );
    }
}
