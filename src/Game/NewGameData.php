<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;

readonly class NewGameData
{
    public const int INITIAL_VOLLEYBALL = 1;
    public const int INITIAL_NET = 1;

    private function __construct(
        public array $gameRow,
        public array $slotRow,
        public array $gamePlayerRow,
        public array $playerRow,
    ) {
    }

    public static function fromUser(
        TelegramUser $creator,
        string $title,
        string $inlineQueryId,
        string $inlineMessageId = '',
    ): self {
        $userId = $creator->id;

        return new self(
            gameRow: [
                'game_id' => 0,
                'inline_query_id' => $inlineQueryId,
                'inline_message_id' => $inlineMessageId,
                'title' => $title,
            ],
            slotRow: [
                'game_id' => 0,
                'telegram_user_id' => $userId,
                'position' => 1,
            ],
            gamePlayerRow: [
                'game_id' => 0,
                'telegram_user_id' => $userId,
                'volleyball' => self::INITIAL_VOLLEYBALL,
                'net' => self::INITIAL_NET,
                'time' => null,
            ],
            playerRow: [
                'telegram_user_id' => $userId,
                'first_name' => $creator->firstName,
                'last_name' => $creator->lastName,
                'username' => $creator->username,
            ],
        );
    }

}
