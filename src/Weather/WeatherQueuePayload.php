<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use JsonSerializable;

final readonly class WeatherQueuePayload implements JsonSerializable
{
    public function __construct(
        public int $gameId,
        public bool $force,
    ) {
    }

    /** @return array{game_id: int, force: bool} */
    public function jsonSerialize(): array
    {
        return [
            'game_id' => $this->gameId,
            'force' => $this->force,
        ];
    }

    /** @param array{game_id: int|string, force: bool|int|string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['game_id'],
            (bool)$data['force'],
        );
    }
}
