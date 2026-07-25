<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location;

/**
 * Recognises venues mentioned in free-text game titles ("вторник 10:00 Somorrostro(!)").
 *
 * Holds an immutable set of venues plus the alias index derived from it, so the index is
 * built once per directory instead of on every lookup.
 */
final readonly class VenueDirectory
{
    /** @var list<VenueAlias> */
    private array $aliasIndex;

    /**
     * @param list<Venue> $venues
     */
    public function __construct(
        private array $venues,
    ) {
        $this->aliasIndex = self::buildAliasIndex($venues);
    }

    /**
     * @return list<Venue>
     */
    public function all(): array
    {
        return $this->venues;
    }

    public function findInTitle(string $title): ?Venue
    {
        $normalizedTitle = self::normalize($title);

        foreach ($this->aliasIndex as $entry) {
            if (str_contains($normalizedTitle, $entry->alias)) {
                return $entry->venue;
            }
        }

        return null;
    }

    /**
     * Every name a venue answers to — its own name included — normalized and paired with the
     * venue, longest first so "Nova Mar Bella" wins over "Mar Bella" when both could match.
     *
     * @param list<Venue> $venues
     *
     * @return list<VenueAlias>
     */
    private static function buildAliasIndex(array $venues): array
    {
        $index = [];

        foreach ($venues as $venue) {
            foreach ([$venue->name, ...$venue->aliases] as $alias) {
                $index[] = new VenueAlias(self::normalize($alias), $venue);
            }
        }

        usort(
            $index,
            static fn(VenueAlias $left, VenueAlias $right): int => mb_strlen($right->alias) <=> mb_strlen($left->alias),
        );

        return $index;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower($value);
    }
}
