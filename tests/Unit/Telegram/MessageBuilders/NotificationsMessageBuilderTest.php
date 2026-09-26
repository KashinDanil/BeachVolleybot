<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NotificationsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;
use UnhandledMatchError;

final class NotificationsMessageBuilderTest extends TestCase
{
    private NotificationsMessageBuilder $builder;

    private string $missingTranslationsFile;

    public function testListShowsHeaderAndChooseText(): void
    {
        $text = $this->buildList(new NotificationSettings())->getText()->getMessageText();

        $this->assertStringContainsString('*Notifications*', $text);
        $this->assertStringContainsString('Choose a notification to set it up\\.', $text);
    }

    // --- list ---

    public function testListHasOneButtonPerTypeInEnumOrder(): void
    {
        $keyboard = $this->extractKeyboard($this->buildList(new NotificationSettings()));

        $this->assertCount(count(NotificationType::cases()), $keyboard);
        $this->assertSame(
            ['✅ Game is on', '⚠️ Short of players', "⬆️ You're in", "⬇️ You're out"],
            array_map(static fn(array $row): string => $row[0]['text'], $keyboard),
            'The list changed. If you added a NotificationType, append its English label to the expected list here.',
        );

        foreach (NotificationType::cases() as $index => $type) {
            $callbackData = UserCallbackData::fromJson($keyboard[$index][0]['callback_data']);
            $this->assertSame(UserCallbackAction::NotificationDetail, $callbackData->getAction());
            $this->assertSame($type, $callbackData->getNotificationType());
        }
    }

    private function buildList(NotificationSettings $settings): TelegramMessage
    {
        return $this->render(fn(): TelegramMessage => $this->builder->buildList($settings));
    }

    private function render(callable $build): TelegramMessage
    {
        try {
            return $build();
        } catch (UnhandledMatchError $error) {
            $this->fail(sprintf(
                "%s.\nAdd an arm for it to the match in NotificationsMessageBuilder::%s(), backed by a new constant, "
                . "and translate that constant's text in every localization/<lang>.json.",
                $error->getMessage(),
                $error->getTrace()[0]['function'],
            ));
        }
    }

    private function extractKeyboard(TelegramMessage $message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    public function testListStylesOnlyEnabledTypesAsSuccess(): void
    {
        $settings = new NotificationSettings()
            ->enable(NotificationType::GameReachedMinimumPlayers)
            ->enable(NotificationType::BumpedFromGame);

        $keyboard = $this->extractKeyboard($this->buildList($settings));

        $this->assertSame('success', $keyboard[0][0]['style'] ?? null);
        $this->assertArrayNotHasKey('style', $keyboard[1][0]);
        $this->assertArrayNotHasKey('style', $keyboard[2][0]);
        $this->assertSame('success', $keyboard[3][0]['style'] ?? null);
    }

    // --- detail ---

    public function testDetailShowsBoldLabel(): void
    {
        $text = $this->detailText(NotificationType::GameReachedMinimumPlayers, new NotificationSettings());

        $this->assertStringStartsWith('*✅ Game is on*', $text);
    }

    private function detailText(NotificationType $type, NotificationSettings $settings): string
    {
        return $this->buildDetail($type, $settings)->getText()->getMessageText();
    }

    private function buildDetail(NotificationType $type, NotificationSettings $settings): TelegramMessage
    {
        return $this->render(fn(): TelegramMessage => $this->builder->buildDetail($type, $settings));
    }

    public function testDisabledDetailSaysYouWontGetTheNotification(): void
    {
        $text = $this->detailText(NotificationType::PromotedIntoGame, new NotificationSettings());

        $this->assertStringContainsString(
            "🔕 You won't get a notification when a spot opens up and you move from the reserve to playing\\.",
            $text,
        );
    }

    public function testEnabledDetailSaysYoullGetTheNotification(): void
    {
        $settings = new NotificationSettings()->enable(NotificationType::GameShortBeforeKickoff);

        $text = $this->detailText(NotificationType::GameShortBeforeKickoff, $settings);

        $this->assertStringContainsString(
            "🔔 You'll get a notification when kickoff is near and a game you've joined still doesn't have enough players\\.",
            $text,
        );
    }

    public function testDisabledDetailOffersEnableAsSuccess(): void
    {
        $keyboard = $this->extractKeyboard(
            $this->buildDetail(NotificationType::BumpedFromGame, new NotificationSettings()),
        );

        $switchButton = $keyboard[0][0];
        $callbackData = UserCallbackData::fromJson($switchButton['callback_data']);

        $this->assertSame('Enable', $switchButton['text']);
        $this->assertSame('success', $switchButton['style']);
        $this->assertSame(UserCallbackAction::EnableNotification, $callbackData->getAction());
        $this->assertSame(NotificationType::BumpedFromGame, $callbackData->getNotificationType());
    }

    public function testEnabledDetailOffersDisableAsDanger(): void
    {
        $settings = new NotificationSettings()->enable(NotificationType::BumpedFromGame);

        $keyboard = $this->extractKeyboard($this->buildDetail(NotificationType::BumpedFromGame, $settings));

        $switchButton = $keyboard[0][0];
        $callbackData = UserCallbackData::fromJson($switchButton['callback_data']);

        $this->assertSame('Disable', $switchButton['text']);
        $this->assertSame('danger', $switchButton['style']);
        $this->assertSame(UserCallbackAction::DisableNotification, $callbackData->getAction());
        $this->assertSame(NotificationType::BumpedFromGame, $callbackData->getNotificationType());
    }

    public function testDetailBackButtonReturnsToTheList(): void
    {
        $keyboard = $this->extractKeyboard(
            $this->buildDetail(NotificationType::BumpedFromGame, new NotificationSettings()),
        );

        $this->assertCount(2, $keyboard);
        $callbackData = UserCallbackData::fromJson($keyboard[1][0]['callback_data']);
        $this->assertSame(UserCallbackAction::NotificationsList, $callbackData->getAction());
    }

    public function testDetailIsTranslated(): void
    {
        $builder = new NotificationsMessageBuilder(new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_')));
        $settings = new NotificationSettings()->enable(NotificationType::GameReachedMinimumPlayers);

        $message = $builder->buildDetail(NotificationType::GameReachedMinimumPlayers, $settings);

        $this->assertStringContainsString('Вы получите уведомление, когда', $message->getText()->getMessageText());
        $this->assertSame('Выключить', $this->extractKeyboard($message)[0][0]['text']);
    }

    // --- every type ---

    public function testEveryTypeHasItsOwnListLabel(): void
    {
        $keyboard = $this->extractKeyboard($this->buildList(new NotificationSettings()));
        $labelsByTypeName = [];

        foreach (NotificationType::cases() as $index => $type) {
            $labelsByTypeName[$type->name] = $keyboard[$index][0]['text'];
        }

        $this->assertEachTypeRendersDifferently($labelsByTypeName, 'NotificationsMessageBuilder::label()');
    }

    /** @param array<string, string> $textsByTypeName */
    private function assertEachTypeRendersDifferently(array $textsByTypeName, string $source): void
    {
        $typeNamesByText = [];

        foreach ($textsByTypeName as $typeName => $text) {
            $typeNamesByText[$text][] = "NotificationType::$typeName";
        }

        foreach ($typeNamesByText as $text => $typeNames) {
            $this->assertCount(1, $typeNames, sprintf(
                "%s render the same text in %s:\n%s\nGive each type its own constant there.",
                implode(' and ', $typeNames),
                $source,
                $text,
            ));
        }
    }

    public function testEveryTypeHasItsOwnDetailInBothStates(): void
    {
        $disabledTextsByTypeName = [];
        $enabledTextsByTypeName = [];

        foreach (NotificationType::cases() as $type) {
            $disabledTextsByTypeName[$type->name] = $this->detailText($type, new NotificationSettings());
            $enabledTextsByTypeName[$type->name] = $this->detailText($type, new NotificationSettings()->enable($type));
        }

        $source = 'NotificationsMessageBuilder::label() or ::trigger()';
        $this->assertEachTypeRendersDifferently($disabledTextsByTypeName, $source);
        $this->assertEachTypeRendersDifferently($enabledTextsByTypeName, $source);
    }

    public function testEveryTypeDetailSwitchesThatSameType(): void
    {
        foreach (NotificationType::cases() as $type) {
            foreach ([new NotificationSettings(), new NotificationSettings()->enable($type)] as $settings) {
                $switchButton = $this->extractKeyboard($this->buildDetail($type, $settings))[0][0];
                $callbackData = UserCallbackData::fromJson($switchButton['callback_data']);

                $this->assertSame(
                    $type,
                    $callbackData->getNotificationType(),
                    "The Enable/Disable button on the NotificationType::$type->name detail switches a different type. "
                    . 'Check NotificationsMessageBuilder::buildSwitchButton().',
                );
            }
        }
    }

    public function testEveryTypeIsTranslatedInEveryLocale(): void
    {
        foreach (Translator::supportedLanguages() as $language) {
            if (Language::EN === $language) {
                continue;
            }

            $builder = new NotificationsMessageBuilder(new Translator($language, $this->missingTranslationsFile));
            $this->render(fn(): TelegramMessage => $builder->buildList(new NotificationSettings()));

            foreach (NotificationType::cases() as $type) {
                $this->render(fn(): TelegramMessage => $builder->buildDetail($type, new NotificationSettings()));
                $this->render(fn(): TelegramMessage => $builder->buildDetail($type, new NotificationSettings()->enable($type)));
            }
        }

        $this->assertEmpty($this->missingTranslations(), $this->describeMissingTranslations());
    }

    /** @return array<string, list<string>> language => untranslated English strings */
    private function missingTranslations(): array
    {
        $contents = (string)@file_get_contents($this->missingTranslationsFile);

        return '' === $contents ? [] : json_decode($contents, true);
    }

    private function describeMissingTranslations(): string
    {
        $lines = ['The notification settings show English text in these locales. Add each string as a key:'];

        foreach ($this->missingTranslations() as $language => $texts) {
            $lines[] = "localization/$language.json:";

            foreach ($texts as $text) {
                $lines[] = '  ' . json_encode($text, JSON_UNESCAPED_UNICODE) . ': "…",';
            }
        }

        return implode("\n", $lines);
    }

    protected function setUp(): void
    {
        $this->builder = new NotificationsMessageBuilder(new Translator());
        $this->missingTranslationsFile = sys_get_temp_dir() . '/bvb_notifications_missing_' . getmypid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->missingTranslationsFile);
    }
}
