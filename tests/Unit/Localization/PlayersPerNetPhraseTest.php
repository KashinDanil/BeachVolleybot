<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Localization;

use BeachVolleybot\Common\Extractors\PlayersPerNetExtractor;
use BeachVolleybot\Localization\PlayersPerNetPhrase;
use BeachVolleybot\Localization\Translator;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayersPerNetPhraseTest extends TestCase
{
    private const int MINIMUM = 4;
    private const int MAXIMUM = 12;

    public function testEnglishUsesOnePluralFormForEveryCount(): void
    {
        $translator = new Translator();

        $this->assertSame('4 spots per net', new PlayersPerNetPhrase(4, $translator)->text());
        $this->assertSame('6 spots per net', new PlayersPerNetPhrase(6, $translator)->text());
        $this->assertSame('12 spots per net', new PlayersPerNetPhrase(12, $translator)->text());
    }

    public function testSpanishUsesOnePluralFormForEveryCount(): void
    {
        $translator = self::translator(Language::ES);

        $this->assertSame('4 plazas por red', new PlayersPerNetPhrase(4, $translator)->text());
        $this->assertSame('6 plazas por red', new PlayersPerNetPhrase(6, $translator)->text());
        $this->assertSame('12 plazas por red', new PlayersPerNetPhrase(12, $translator)->text());
    }

    public function testRussianSwitchesNounFormByLastDigit(): void
    {
        $translator = self::translator(Language::RU);

        $this->assertSame('4 человека на сетку', new PlayersPerNetPhrase(4, $translator)->text());
        $this->assertSame('5 человек на сетку', new PlayersPerNetPhrase(5, $translator)->text());
    }

    public function testRussianTeensStayInTheManyFormEvenThoughTheLastDigitLooksLikeFew(): void
    {
        $translator = self::translator(Language::RU);

        // 12, 13, 14 end in 2/3/4 but are "-надцать" teens, which always take the "many" form.
        $this->assertSame('12 человек на сетку', new PlayersPerNetPhrase(12, $translator)->text());
    }

    /**
     * The wizard itself never goes past 12, but a hand-typed title can carry any count the
     * minimum rule allows — 21, 31, 101… all end in a bare "1" and take the singular form.
     */
    public function testRussianUsesTheSingularFormForCountsEndingInOne(): void
    {
        $translator = self::translator(Language::RU);

        $this->assertSame('21 человек на сетку', new PlayersPerNetPhrase(21, $translator)->text());
        $this->assertSame('101 человек на сетку', new PlayersPerNetPhrase(101, $translator)->text());
    }

    public function testRussianElevenStaysInTheManyFormDespiteEndingInOne(): void
    {
        $translator = self::translator(Language::RU);

        $this->assertSame('11 человек на сетку', new PlayersPerNetPhrase(11, $translator)->text());
        $this->assertSame('111 человек на сетку', new PlayersPerNetPhrase(111, $translator)->text());
    }

    /**
     * Key guard: every phrase the wizard can ever put on the button or in the title row must
     * be read back by the same extractor the title→settings pipeline relies on, or a posted
     * limit would silently vanish on the very next parse. Also covers counts the wizard's own
     * dial never reaches but a hand-typed title can (20-25), since every Russian plural
     * category — one, few and many — needs its own round trip proven, not just 4-12's.
     *
     * Stops at two digits: PlayersPerNetExtractor deliberately never reads a three-digit count
     * (@see PlayersPerNetExtractorTest::testThreeDigitCountReturnsNull), so 101, 111 and the
     * like are exercised directly against PlayersPerNetPhrase instead, without the extractor.
     */
    #[DataProvider('everyValueWorthRoundTripping')]
    public function testEveryPhraseRoundTripsThroughTheExtractor(int $count): void
    {
        foreach ([new Translator(), self::translator(Language::RU), self::translator(Language::ES)] as $translator) {
            $phrase = new PlayersPerNetPhrase($count, $translator)->text();

            $this->assertSame(
                $count,
                PlayersPerNetExtractor::resolvePlayersPerNet($phrase),
                "'$phrase' (language '{$translator->language()}') did not round-trip to $count",
            );
        }
    }

    /** @return array<string, array{int}> */
    public static function everyValueWorthRoundTripping(): array
    {
        $cases = [];

        foreach ([...range(self::MINIMUM, self::MAXIMUM), 20, 21, 22, 24, 25] as $count) {
            $cases["$count spots per net"] = [$count];
        }

        return $cases;
    }

    private static function translator(string $language): Translator
    {
        return new Translator($language, tempnam(sys_get_temp_dir(), 'bvb_missing_'));
    }
}
