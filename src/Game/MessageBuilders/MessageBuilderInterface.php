<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\MessageBuilders;

use BeachVolleybot\Game\GameInterface;
use BeachVolleybot\Telegram\Outgoing\TelegramMessage;

interface MessageBuilderInterface
{
    public function build(GameInterface $game): TelegramMessage;
}
