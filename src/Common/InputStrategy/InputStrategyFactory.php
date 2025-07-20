<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\InputStrategy;

class InputStrategyFactory
{
    public static function getStrategy(): InputStrategy
    {
        if (php_sapi_name() === 'cli') {
            return new CliInputStrategy();
        }

        return new WebInputStrategy();
    }
}
