<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

abstract class AbstractAdminMessageBuilder extends AbstractMessageBuilder
{
    protected function formatHeader(string $header): string
    {
        return $this->formatter->bold($header);
    }
}
