<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UsersListMessageBuilder extends AbstractAdminMessageBuilder
{
    private const int USERS_PER_PAGE = 8;

    public function build(GameInterface $game, int $page): TelegramMessage
    {
        $gameId = $game->getGameId();
        [$uniqueUsers, $slotCounts] = $this->aggregateUserSlots($game->getUsers());

        $pagination = new KeyboardPagination(count($uniqueUsers), self::USERS_PER_PAGE, $page);
        $pageUsers = array_slice($uniqueUsers, $pagination->getOffset(), self::USERS_PER_PAGE);

        return $this->buildMessage(
            $this->buildUsersListText($gameId, $game->getTitle(), $pagination),
            $this->buildUsersListKeyboard($pageUsers, $slotCounts, $gameId, $pagination),
        );
    }

    /**
     * @param UserInterface[] $users
     *
     * @return array{UserInterface[], array<int, int>}
     */
    private function aggregateUserSlots(array $users): array
    {
        $uniqueUsers = [];
        $slotCounts = [];

        foreach ($users as $user) {
            $userId = $user->getTelegramUserId();

            if (!isset($slotCounts[$userId])) {
                $slotCounts[$userId] = 0;
                $uniqueUsers[] = $user;
            }

            $slotCounts[$userId]++;
        }

        return [$uniqueUsers, $slotCounts];
    }

    private function buildUsersListText(int $gameId, string $gameTitle, KeyboardPagination $pagination): string
    {
        return $this->formatHeader("Users #$gameId")
            . $this->formatter->newLine() . $this->formatter->blockquote($this->formatter->escape($gameTitle))
            . $this->formatter->newLine() . $this->formatter->escape("Page {$pagination->getPage()} of {$pagination->getTotalPages()}");
    }

    /**
     * @param UserInterface[] $pageUsers
     * @param array<int, int> $slotCounts
     */
    private function buildUsersListKeyboard(array $pageUsers, array $slotCounts, int $gameId, KeyboardPagination $pagination): array
    {
        $keyboard = $this->buildUserRows($pageUsers, $slotCounts, $gameId);

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
     * @param UserInterface[] $pageUsers
     * @param array<int, int> $slotCounts
     */
    private function buildUserRows(array $pageUsers, array $slotCounts, int $gameId): array
    {
        $rows = [];

        foreach ($pageUsers as $user) {
            $userId = $user->getTelegramUserId();
            $name = $this->buildUserLabel($user, $slotCounts[$userId]);

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

    private function buildUserLabel(UserInterface $user, int $slotCount): string
    {
        $name = $user->getName();

        if (1 < $slotCount) {
            return "$name (x$slotCount)";
        }

        return $name;
    }
}
