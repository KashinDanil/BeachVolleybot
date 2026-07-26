<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Targets;

final readonly class InlineGameMessageTarget implements GameMessageTarget
{
    public function __construct(
        public string $inlineMessageId,
    ) {
    }
}
