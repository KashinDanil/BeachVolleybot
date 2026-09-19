<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use JsonSerializable;

final readonly class GameSettings implements JsonSerializable
{
    private const string PLAYERS_PER_NET_KEY = 'players_per_net';

    public function __construct(
        public ?int $playersPerNet = null,
    ) {
    }

    public static function fromJson(?string $json): self
    {
        if (null === $json || '' === $json) {
            return new self();
        }

        $data = json_decode($json, associative: true);

        return is_array($data) ? self::fromArray($data) : new self();
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data[self::PLAYERS_PER_NET_KEY] ?? null
        );
    }

    public function withPlayersPerNet(?int $playersPerNet): self
    {
        return new self(playersPerNet: $playersPerNet);
    }

    /** @return array<string, ?int> */
    public function jsonSerialize(): array
    {
        return [self::PLAYERS_PER_NET_KEY => $this->playersPerNet];
    }
}
