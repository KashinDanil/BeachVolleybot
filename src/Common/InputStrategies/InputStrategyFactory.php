<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\InputStrategies;

class InputStrategyFactory
{
    public static function getStrategy(): AbstractInputStrategy
    {
        if (PHP_SAPI === 'cli') {
            return new CliInputStrategy();
        }

        return new WebInputStrategy();
    }
}
