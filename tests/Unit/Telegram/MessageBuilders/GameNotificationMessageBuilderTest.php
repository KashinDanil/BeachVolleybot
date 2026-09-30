<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\GameNotificationMessageBuilder;
use BeachVolleybot\Telegram\PlainText;
use BeachVolleybot\User\NotificationType;
use DanilKashin\Localization\Language;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class GameNotificationMessageBuilderTest extends TestCase
{
    private const string NOW     = '2026-09-06 12:00';
    private const string KICKOFF = '2026-08-14 18:00';

    private string $missingTranslationsFile;

    protected function setUp(): void
    {
        $this->missingTranslationsFile = BASE_LOG_DIR . '/game_notification_missing_' . getmypid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->missingTranslationsFile);
    }

    public function testRendersThePromotedIntoGameMarkdownV2Message(): void
    {
        $message = new GameNotificationMessageBuilder($this->translator(Language::EN), new DateTimeImmutable(self::NOW))
            ->build(NotificationType::PromotedIntoGame, $this->game('Friday 18:00 Barceloneta'));

        $this->assertSame(
            "*⬆️ You're in*\n\nA spot opened up, and you're now playing on Friday, 14 Aug at 18:00:\n>Friday 18:00 Barceloneta",
            $message->getText()->getMessageText(),
        );
        $this->assertSame('MarkdownV2', $message->getText()->getParseMode());
    }

    public function testRendersTheShortBeforeKickoffMarkdownV2Message(): void
    {
        $message = new GameNotificationMessageBuilder($this->translator(Language::EN), new DateTimeImmutable(self::NOW))
            ->build(NotificationType::GameShortBeforeKickoff, $this->game('Friday 18:00 Barceloneta'));

        $this->assertSame(
            "*⚠️ Short of players*\n\nThe game on Friday, 14 Aug at 18:00 starts soon, but there still aren't enough players:\n>Friday 18:00 Barceloneta",
            $message->getText()->getMessageText(),
        );
    }

    public function testRendersTheKickoffTimeChangedHintBelowTheQuotedTitle(): void
    {
        $message = new GameNotificationMessageBuilder($this->translator(Language::EN), new DateTimeImmutable(self::NOW))
            ->build(NotificationType::KickoffTimeChanged, $this->game('Friday 18:00 Barceloneta'));

        $this->assertStringEndsWith(
            ">Friday 18:00 Barceloneta\n\nTo change your own time in the game, reply to the game message in the chat with the time you'll arrive, like 19:30\\.",
            $message->getText()->getMessageText(),
        );
    }

    public function testEscapesMarkdownCharactersInTheTitle(): void
    {
        $text = new GameNotificationMessageBuilder($this->translator(Language::EN), new DateTimeImmutable(self::NOW))
            ->build(NotificationType::PromotedIntoGame, $this->game('*Beach* _game_ [18:00] ~ok~ `x` {y} | (net #2) 14.08!'))
            ->getText()
            ->getMessageText();

        $this->assertStringEndsWith(
            "\n>\\*Beach\\* \\_game\\_ \\[18:00\\] \\~ok\\~ \\`x\\` \\{y\\} \\| \\(net \\#2\\) 14\\.08\\!",
            $text,
        );
    }

    public function testDisablesTheLinkPreview(): void
    {
        $message = new GameNotificationMessageBuilder($this->translator(Language::EN), new DateTimeImmutable(self::NOW))
            ->build(NotificationType::PromotedIntoGame, $this->game());

        $this->assertTrue($message->getText()->isDisableWebPagePreview());
    }

    public function testCarriesNoKeyboardButtons(): void
    {
        $message = new GameNotificationMessageBuilder($this->translator(Language::EN), new DateTimeImmutable(self::NOW))
            ->build(NotificationType::GameShortBeforeKickoff, $this->game());

        $this->assertSame([], json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard']);
    }

    public function testRendersEveryTypeInEnglish(): void
    {
        $this->assertSame(
            "✅ Game is on\n\nEnough players have joined, so the game on Friday, 14 Aug at 18:00 will take place:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::GameReachedMinimumPlayers, Language::EN),
        );
        $this->assertSame(
            "⚠️ Short of players\n\nThe game on Friday, 14 Aug at 18:00 starts soon, but there still aren't enough players:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::GameShortBeforeKickoff, Language::EN),
        );
        $this->assertSame(
            "⬆️ You're in\n\nA spot opened up, and you're now playing on Friday, 14 Aug at 18:00:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::PromotedIntoGame, Language::EN),
        );
        $this->assertSame(
            "⬇️ You're out\n\nYou've moved to the reserve and are no longer playing on Friday, 14 Aug at 18:00:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::BumpedFromGame, Language::EN),
        );
        $this->assertSame(
            "🕒 Game time changed\n\nThe game time has changed, and it now takes place on Friday, 14 Aug at 18:00:\nFriday 18:00 Barceloneta\n\n"
            . "To change your own time in the game, reply to the game message in the chat with the time you'll arrive, like 19:30.",
            $this->plainText(NotificationType::KickoffTimeChanged, Language::EN),
        );
    }

    public function testRendersEveryTypeInRussian(): void
    {
        $this->assertSame(
            "✅ Игра состоится\n\nНабралось достаточно игроков — игра в пятницу, 14 авг в 18:00 состоится:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::GameReachedMinimumPlayers, Language::RU),
        );
        $this->assertSame(
            "⚠️ Не хватает игроков\n\nИгра в пятницу, 14 авг в 18:00 скоро начнётся, но игроков всё ещё не хватает:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::GameShortBeforeKickoff, Language::RU),
        );
        $this->assertSame(
            "⬆️ Вы в игре\n\nОсвободилось место, и теперь вы играете в пятницу, 14 авг в 18:00:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::PromotedIntoGame, Language::RU),
        );
        $this->assertSame(
            "⬇️ Вы не играете\n\nВы перешли в запас и больше не играете в пятницу, 14 авг в 18:00:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::BumpedFromGame, Language::RU),
        );
        $this->assertSame(
            "🕒 Время игры изменилось\n\nВремя игры изменилось — теперь она пройдёт в пятницу, 14 авг в 18:00:\nFriday 18:00 Barceloneta\n\n"
            . "Чтобы изменить своё время в игре, ответьте на сообщение с игрой в чате, указав время, когда придёте, например 19:30.",
            $this->plainText(NotificationType::KickoffTimeChanged, Language::RU),
        );
    }

    public function testRendersEveryTypeInSpanish(): void
    {
        $this->assertStringEndsWith(
            "Ya hay suficientes jugadores y el partido se jugará el viernes, 14 ago a las 18:00:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::GameReachedMinimumPlayers, Language::ES),
        );
        $this->assertStringEndsWith(
            "Pronto empieza el partido el viernes, 14 ago a las 18:00, pero aún faltan jugadores:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::GameShortBeforeKickoff, Language::ES),
        );
        $this->assertStringEndsWith(
            "Se liberó un lugar y ahora juegas el viernes, 14 ago a las 18:00:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::PromotedIntoGame, Language::ES),
        );
        $this->assertStringEndsWith(
            "Pasaste a la reserva y ya no juegas el viernes, 14 ago a las 18:00:\nFriday 18:00 Barceloneta",
            $this->plainText(NotificationType::BumpedFromGame, Language::ES),
        );
        $this->assertStringEndsWith(
            "El partido cambió de hora y ahora se juega el viernes, 14 ago a las 18:00:\nFriday 18:00 Barceloneta\n\n"
            . "Para cambiar tu propia hora en el partido, responde al mensaje del partido en el chat indicando la hora a la que llegarás, como 19:30.",
            $this->plainText(NotificationType::KickoffTimeChanged, Language::ES),
        );
    }

    public function testEveryTypeAndWeekdayIsTranslatedInEveryLocale(): void
    {
        foreach ([Language::RU, Language::ES] as $language) {
            @unlink($this->missingTranslationsFile);
            $builder = new GameNotificationMessageBuilder(
                $this->translator($language),
                new DateTimeImmutable(self::NOW),
                new PlainText(),
            );

            foreach (NotificationType::cases() as $type) {
                foreach (range(10, 16) as $dayOfAugust) {
                    $builder->build($type, $this->game(kickoffAt: "2026-08-$dayOfAugust 18:00"));
                }
            }

            $this->assertFileDoesNotExist($this->missingTranslationsFile, "Missing $language translations");
        }
    }

    public function testEachTypeSaysSomethingDifferent(): void
    {
        $texts = array_map(
            fn(NotificationType $type): string => $this->plainText($type, Language::EN),
            NotificationType::cases(),
        );

        $this->assertSame($texts, array_unique($texts));
    }

    public function testSpellsTheKickoffOnTheVenueWallClock(): void
    {
        $kickoffAtTheVenue = new DateTimeImmutable('2026-08-14 16:00', new DateTimeZone('UTC'))
            ->setTimezone(new DateTimeZone('Europe/Madrid'));

        $text = new GameNotificationMessageBuilder($this->translator(Language::EN), new DateTimeImmutable(self::NOW), new PlainText())
            ->build(NotificationType::PromotedIntoGame, $this->game(kickoffAt: $kickoffAtTheVenue->format(DATE_ATOM)))
            ->getText()
            ->getMessageText();

        $this->assertStringContainsString('on Friday, 14 Aug at 18:00:', $text);
    }

    public function testOmitsTheYearWithinTheCurrentYearByDefault(): void
    {
        $kickoffAt = new DateTimeImmutable('today 18:00');

        $text = new GameNotificationMessageBuilder($this->translator(Language::EN), formatter: new PlainText())
            ->build(NotificationType::PromotedIntoGame, $this->game(kickoffAt: $kickoffAt->format('Y-m-d H:i')))
            ->getText()
            ->getMessageText();

        $this->assertStringNotContainsString($kickoffAt->format('Y'), $text);
    }

    public function testShowsTheYearForAGameInAnotherYear(): void
    {
        $text = $this->plainText(NotificationType::PromotedIntoGame, Language::EN, kickoffAt: '2027-08-13 18:00');

        $this->assertStringContainsString("you're now playing on Friday, 13 Aug 2027 at 18:00:", $text);
    }

    public function testNeverShowsTheGameNumber(): void
    {
        foreach (NotificationType::cases() as $type) {
            $this->assertStringNotContainsString('#42', $this->plainText($type, Language::EN));
        }
    }

    private function plainText(NotificationType $type, string $language, string $kickoffAt = self::KICKOFF): string
    {
        return new GameNotificationMessageBuilder($this->translator($language), new DateTimeImmutable(self::NOW), new PlainText())
            ->build($type, $this->game(kickoffAt: $kickoffAt))
            ->getText()
            ->getMessageText();
    }

    private function game(string $title = 'Friday 18:00 Barceloneta', string $kickoffAt = self::KICKOFF): GameRecord
    {
        return new GameRecord(
            gameId: 42,
            gameKey: 'query_1',
            createdBy: 100,
            title: $title,
            createdAt: new DateTimeImmutable('2026-08-01 10:00'),
            kickoffAt: new DateTimeImmutable($kickoffAt),
        );
    }

    private function translator(string $language): Translator
    {
        return new Translator($language, $this->missingTranslationsFile);
    }
}
