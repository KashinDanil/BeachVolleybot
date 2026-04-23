<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns;

use BeachVolleybot\Game\AddOns\GameAddOnRegistry;
use BeachVolleybot\Game\AddOns\MergeConsecutiveSlotsAddOn;
use BeachVolleybot\Game\AddOns\StylizeTitleAddOn;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use PHPUnit\Framework\TestCase;

final class GameAddOnRegistryTest extends TestCase
{
    public function testReturnsTrueWhenAddOnIsInList(): void
    {
        $this->assertTrue(
            GameAddOnRegistry::isEnabled(WeatherAddOn::class, [WeatherAddOn::class, StylizeTitleAddOn::class]),
        );
    }

    public function testReturnsFalseWhenAddOnIsNotInList(): void
    {
        $this->assertFalse(
            GameAddOnRegistry::isEnabled(WeatherAddOn::class, [MergeConsecutiveSlotsAddOn::class]),
        );
    }

    public function testReturnsFalseForEmptyList(): void
    {
        $this->assertFalse(GameAddOnRegistry::isEnabled(WeatherAddOn::class, []));
    }
}
