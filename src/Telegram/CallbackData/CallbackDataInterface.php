<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use JsonSerializable;

interface CallbackDataInterface extends JsonSerializable
{
    public function toJson(): string;
}
