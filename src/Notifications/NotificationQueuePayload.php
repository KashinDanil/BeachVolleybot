<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use BeachVolleybot\User\NotificationType;
use JsonSerializable;

/** One notification for one user about one game; a producer enqueues one payload per recipient. */
final readonly class NotificationQueuePayload implements JsonSerializable
{
    public function __construct(
        public NotificationType $type,
        public int $gameId,
        public int $userId,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $typeValue = $data['type'] ?? null;
        if (!is_int($typeValue)) {
            return null;
        }

        $type = NotificationType::tryFrom($typeValue);
        if (null === $type) {
            return null;
        }

        $gameId = $data['game_id'] ?? null;
        if (!is_int($gameId)) {
            return null;
        }

        $userId = $data['user_id'] ?? null;
        if (!is_int($userId)) {
            return null;
        }

        return new self($type, $gameId, $userId);
    }

    /** @return array{type: int, game_id: int, user_id: int} */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'game_id' => $this->gameId,
            'user_id' => $this->userId,
        ];
    }
}
