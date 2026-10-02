<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Admin;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\KeyboardPagination;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UsersListMessageBuilder extends AbstractAdminMessageBuilder
{
    private const int PLAYERS_PER_PAGE = 8;

    public function build(GameInterface $game, int $page): TelegramMessage
    {
        $gameId = $game->getGameId();
        [$uniquePlayers, $slotCounts] = $this->aggregatePlayerSlots($game->getPlayers());

        $pagination = new KeyboardPagination(count($uniquePlayers), self::PLAYERS_PER_PAGE, $page);
        $pagePlayers = array_slice($uniquePlayers, $pagination->getOffset(), self::PLAYERS_PER_PAGE);

        return $this->buildMessage(
            $this->buildUsersListText($gameId, $game->getTitle(), $pagination),
            $this->buildUsersListKeyboard($pagePlayers, $slotCounts, $gameId, $pagination),
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

    private function buildUsersListText(int $gameId, string $gameTitle, KeyboardPagination $pagination): string
    {
        return $this->formatHeader("Users #$gameId")
            . $this->formatter->newLine() . $this->formatter->blockquote($this->formatter->escape($gameTitle))
            . $this->formatter->newLine() . $this->formatter->escape("Page {$pagination->getPage()} of {$pagination->getTotalPages()}");
    }

    /**
     * @param PlayerInterface[] $pagePlayers
     * @param array<int, int> $slotCounts
     */
    private function buildUsersListKeyboard(array $pagePlayers, array $slotCounts, int $gameId, KeyboardPagination $pagination): array
    {
        $keyboard = $this->buildPlayerRows($pagePlayers, $slotCounts, $gameId);

        $paginationRow = $this->paginationRow(
            $pagination,
            AdminCallbackData::create(AdminCallbackAction::GameUsers)->withGameId($gameId),
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
                    AdminCallbackData::create(AdminCallbackAction::UserSettings)
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
