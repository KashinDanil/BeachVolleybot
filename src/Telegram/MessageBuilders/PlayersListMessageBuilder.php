<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class PlayersListMessageBuilder extends AbstractAdminMessageBuilder
{
    private const int PLAYERS_PER_PAGE = 8;

    public function build(GameInterface $game, int $page): TelegramMessage
    {
        $gameId = $game->getGameId();
        [$uniquePlayers, $slotCounts] = $this->aggregatePlayerSlots($game->getPlayers());

        $pagination = new KeyboardPagination(count($uniquePlayers), self::PLAYERS_PER_PAGE, $page);
        $pagePlayers = array_slice($uniquePlayers, $pagination->offset, self::PLAYERS_PER_PAGE);

        return $this->buildMessage(
            $this->buildPlayersListText($gameId, $game->getTitle(), $pagination),
            $this->buildPlayersListKeyboard($pagePlayers, $slotCounts, $gameId, $pagination),
        );
    }

    /**
     * @param PlayerInterface[] $players
     *
     * @return array{PlayerInterface[], array<int, int>}
     */
    private function aggregatePlayerSlots(array $players): array
    {
        $uniquePlayers = [];
        $slotCounts = [];

        foreach ($players as $player) {
            $userId = $player->getTelegramUserId();

            if (!isset($slotCounts[$userId])) {
                $slotCounts[$userId] = 0;
                $uniquePlayers[] = $player;
            }

            $slotCounts[$userId]++;
        }

        return [$uniquePlayers, $slotCounts];
    }

    private function buildPlayersListText(int $gameId, string $gameTitle, KeyboardPagination $pagination): string
    {
        return $this->formatHeader("Players #$gameId")
            . $this->formatter->newLine() . $this->formatter->escape($gameTitle)
            . $this->formatter->newLine() . $this->formatter->escape("Page $pagination->page of $pagination->totalPages");
    }

    /**
     * @param PlayerInterface[] $pagePlayers
     * @param array<int, int> $slotCounts
     */
    private function buildPlayersListKeyboard(array $pagePlayers, array $slotCounts, int $gameId, KeyboardPagination $pagination): array
    {
        $keyboard = $this->buildPlayerRows($pagePlayers, $slotCounts, $gameId);

        $paginationRow = $this->paginationRow(
            $pagination,
            AdminCallbackData::create(AdminCallbackAction::GamePlayers)->withGameId($gameId),
        );
        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        $keyboard[] = $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::GameDetail)->withGameId($gameId));

        return $keyboard;
    }

    /**
     * @param PlayerInterface[] $pagePlayers
     * @param array<int, int> $slotCounts
     */
    private function buildPlayerRows(array $pagePlayers, array $slotCounts, int $gameId): array
    {
        $rows = [];

        foreach ($pagePlayers as $player) {
            $userId = $player->getTelegramUserId();
            $name = $this->buildPlayerLabel($player, $slotCounts[$userId]);

            $rows[] = [
                $this->buildActionButton(
                    $name,
                    AdminCallbackData::create(AdminCallbackAction::PlayerSettings)
                        ->withGameId($gameId)
                        ->withUserId($userId),
                ),
            ];
        }

        return $rows;
    }

    private function buildPlayerLabel(PlayerInterface $player, int $slotCount): string
    {
        $name = $player->getName();

        if (1 < $slotCount) {
            return "$name (x$slotCount)";
        }

        return $name;
    }
}
