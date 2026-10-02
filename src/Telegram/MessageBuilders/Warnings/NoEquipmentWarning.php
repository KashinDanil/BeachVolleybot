<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Localization\Translator;

final class NoEquipmentWarning implements GameWarningInterface
{
    private const string MISSING_NET        = 'Someone needs to bring a net';
    private const string MISSING_VOLLEYBALL = 'Someone needs to bring a volleyball';
    private const string MISSING_BOTH       = 'Someone needs to bring a net and a volleyball';

    public function check(GameInterface $game, Translator $translator): ?string
    {
        $players = $game->getPlayers();
        if (empty($players)) {
            return null;
        }

        $hasNet = array_any($players, static fn(PlayerInterface $player) => 0 < $player->getNet());
        $hasVolleyball = array_any($players, static fn(PlayerInterface $player) => 0 < $player->getVolleyball());

        $message = match (true) {
            $hasNet && $hasVolleyball => null,
            !$hasNet && !$hasVolleyball => self::MISSING_BOTH,
            !$hasNet => self::MISSING_NET,
            default => self::MISSING_VOLLEYBALL,
        };

        if (null === $message) {
            return null;
        }

        return $translator->translate($message);
    }
}
