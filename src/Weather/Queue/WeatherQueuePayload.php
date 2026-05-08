<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Queue;

use JsonSerializable;

final readonly class WeatherQueuePayload implements JsonSerializable
{
    public function __construct(
        public int $gameId,
    ) {
    }

    /** @return array{game_id: int} */
    public function jsonSerialize(): array
    {
        return [
            'game_id' => $this->gameId,
        ];
    }

    /** @param array{game_id: int|string} $data */
    public static function fromArray(array $data): self
    {
        return new self((int)$data['game_id']);
    }
}
