<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Localization\Translator;

final class GameWarningCollector
{
    /** @var list<GameWarningInterface> */
    private readonly array $warnings;

    public function __construct(GameWarningInterface ...$warnings)
    {
        $this->warnings = $warnings;
    }

    /** @return list<string> */
    public function collect(GameInterface $game, Translator $translator): array
    {
        return array_map(
                static fn(GameWarningInterface $warning) => $warning->check($game, $translator),
                $this->warnings,
            )
                |> array_filter(...)
                |> array_values(...);
    }
}
