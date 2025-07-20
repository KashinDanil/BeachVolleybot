<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\InputStrategy;

class CliInputStrategy extends InputStrategy
{
    public function __construct()
    {
        global $argv;
        array_shift($argv);
        $params = [];
        foreach ($argv as $arg) {
            if (preg_match('/--([^=]+)=(.*)/', $arg, $matches)) {
                $params[$matches[1]] = $matches[2];
            }
        }

        $this->secretToken = $params['secretToken'] ?? '';
        $this->payload = $params['payload'] ?? '';
    }
}
