<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Targets;

final readonly class ChatGameMessageTarget implements GameMessageTarget
{
    public function __construct(
        public int $chatId,
        public int $messageId,
    ) {
    }
}
