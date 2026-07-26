<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\KnownVenueRule;
use PHPUnit\Framework\TestCase;

final class KnownVenueRuleTest extends TestCase
{
    public function testAcceptsAKnownVenue(): void
    {
        $this->assertTrue(new KnownVenueRule('Bogatell')->isValid());
    }

    public function testRejectsAnUnknownVenue(): void
    {
        $this->assertFalse(new KnownVenueRule('Atlantis')->isValid());
    }

    public function testRejectsNull(): void
    {
        $this->assertFalse(new KnownVenueRule(null)->isValid());
    }
}
