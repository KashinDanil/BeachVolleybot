<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Localization\Translator;

interface GameWarningInterface
{
    public function check(GameInterface $game, Translator $translator): ?string;
}
