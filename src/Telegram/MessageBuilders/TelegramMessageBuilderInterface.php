<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

interface TelegramMessageBuilderInterface
{
    public function build(GameInterface $game): TelegramMessage;
}
