<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\InputStrategies;

class WebInputStrategy extends AbstractInputStrategy
{
    public function __construct()
    {
        $this->secretToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        $this->payload = (string)file_get_contents('php://input');
    }

    public function getRequestMethod(): ?string
    {
        return $_SERVER['REQUEST_METHOD'] ?? null;
    }
}
