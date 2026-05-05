<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

interface PageableCallbackDataInterface extends CallbackDataInterface
{
    public function withPage(int $page): static;
}
