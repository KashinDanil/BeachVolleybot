<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Fixtures;

use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

trait CreatesGameRecords
{
    /** The kickoff is the venue's wall clock, the way GameRecord::fromRow hands it out. */
    protected function gameRecord(
        int $gameId = 1,
        string $title = 'Bogatell 31.12.2099 18:00',
        string $kickoffAt = '2099-12-31 18:00:00',
        ?string $venueName = 'Bogatell',
    ): GameRecord {
        return new GameRecord(
            gameId: $gameId,
            gameKey: 'query_' . $gameId,
            createdBy: 100,
            title: $title,
            createdAt: new DateTimeImmutable('2099-12-01 10:00:00'),
            kickoffAt: new DateTimeImmutable($kickoffAt, KnownVenues::findByNameOrDefault($venueName)->timezone),
            venueName: $venueName,
        );
    }
}
