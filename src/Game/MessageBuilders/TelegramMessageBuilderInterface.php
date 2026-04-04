<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Outgoing\TelegramMessage;

interface TelegramMessageBuilderInterface
{
    public function build(GameInterface $game): TelegramMessage;
}
