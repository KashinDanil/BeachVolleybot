<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use BeachVolleybot\Localization\PluralCategory;
use BeachVolleybot\Localization\PluralForms;
use PHPUnit\Framework\TestCase;

final class PluralFormsTest extends TestCase
{
    public function testABareWordAnswersEveryCategoryAsOther(): void
    {
        $forms = new PluralForms('jugadores');

        $this->assertSame('jugadores', $forms->get(PluralCategory::Other));
        $this->assertSame('jugadores', $forms->get(PluralCategory::One));
        $this->assertSame('jugadores', $forms->get(PluralCategory::Few));
    }

    public function testEachListedCategoryReturnsItsOwnWord(): void
    {
        $forms = new PluralForms('one:игрок;few:игрока;many:игроков');

        $this->assertSame('игрок', $forms->get(PluralCategory::One));
        $this->assertSame('игрока', $forms->get(PluralCategory::Few));
        $this->assertSame('игроков', $forms->get(PluralCategory::Many));
    }

    public function testPairOrderDoesNotMatter(): void
    {
        $forms = new PluralForms('many:игроков;one:игрок;few:игрока');

        $this->assertSame('игрок', $forms->get(PluralCategory::One));
        $this->assertSame('игрока', $forms->get(PluralCategory::Few));
        $this->assertSame('игроков', $forms->get(PluralCategory::Many));
    }

    public function testACategoryNotListedFallsBackToOther(): void
    {
        // Zero and Two are never written for Russian, since it has no separate word for them.
        $forms = new PluralForms('one:игрок;many:игроков;other:игроков');

        $this->assertSame('игроков', $forms->get(PluralCategory::Zero));
        $this->assertSame('игроков', $forms->get(PluralCategory::Two));
    }
}
