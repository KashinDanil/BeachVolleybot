<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\InputStrategy;

class WebInputStrategy extends InputStrategy
{
    public function __construct()
    {
        $this->secretToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        $this->payload = (string)file_get_contents('php://input');
    }
}
