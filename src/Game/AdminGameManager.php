<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

readonly class AdminGameManager extends GameManager
{
    public function adminAddNet(int $gameId, int $telegramUserId): EquipmentResult
    {
        if (!$this->gamePlayerRepository->exists($gameId, $telegramUserId)) {
            return EquipmentResult::NotJoined;
        }

        return $this->incrementNet($gameId, $telegramUserId);
    }

    public function adminAddVolleyball(int $gameId, int $telegramUserId): EquipmentResult
    {
        if (!$this->gamePlayerRepository->exists($gameId, $telegramUserId)) {
            return EquipmentResult::NotJoined;
        }

        return $this->incrementVolleyball($gameId, $telegramUserId);
    }
}
