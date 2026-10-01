<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

readonly class AdminGameManager extends GameManager
{
    public function adminAddSlot(int $gameId, int $telegramUserId): void
    {
        if (!$this->gameUserManager->isUserInGame($gameId, $telegramUserId)) {
            return;
        }

        $this->gameSlotManager->addSlot($gameId, $telegramUserId);

        $this->minimumPlayersNotifier->notifyIfReached($gameId, $telegramUserId);
    }

    public function adminAddNet(int $gameId, int $telegramUserId): EquipmentResult
    {
        if (!$this->gameUserManager->isUserInGame($gameId, $telegramUserId)) {
            return EquipmentResult::NotJoined;
        }

        return $this->incrementNet($gameId, $telegramUserId);
    }

    public function adminAddVolleyball(int $gameId, int $telegramUserId): EquipmentResult
    {
        if (!$this->gameUserManager->isUserInGame($gameId, $telegramUserId)) {
            return EquipmentResult::NotJoined;
        }

        return $this->incrementVolleyball($gameId, $telegramUserId);
    }
}
