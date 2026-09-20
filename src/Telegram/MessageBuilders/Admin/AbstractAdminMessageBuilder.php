<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Admin;

use BeachVolleybot\Telegram\MessageBuilders\AbstractMessageBuilder;

abstract class AbstractAdminMessageBuilder extends AbstractMessageBuilder
{
    protected function formatHeader(string $header): string
    {
        return $this->formatter->bold($header);
    }
}
