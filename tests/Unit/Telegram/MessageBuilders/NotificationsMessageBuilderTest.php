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

final class NotificationsMessageBuilderTest extends TestCase
{
    private NotificationsMessageBuilder $builder;

    public function testListShowsHeaderAndChooseText(): void
    {
        $text = $this->builder->buildList(new NotificationSettings())->getText()->getMessageText();

        $this->assertStringContainsString('*Notifications*', $text);
        $this->assertStringContainsString('Choose a notification to set it up\\.', $text);
    }

    // --- list ---

    public function testListHasOneButtonPerTypeInEnumOrder(): void
    {
        $keyboard = $this->extractKeyboard($this->builder->buildList(new NotificationSettings()));

        $this->assertCount(count(NotificationType::cases()), $keyboard);
        $this->assertSame(
            ['✅ Game is on', '⚠️ Short of players', "⬆️ You're in", "⬇️ You're out"],
            array_map(static fn(array $row): string => $row[0]['text'], $keyboard),
        );

        foreach (NotificationType::cases() as $index => $type) {
            $callbackData = UserCallbackData::fromJson($keyboard[$index][0]['callback_data']);
            $this->assertSame(UserCallbackAction::NotificationDetail, $callbackData->getAction());
            $this->assertSame($type, $callbackData->getNotificationType());
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

        $keyboard = $this->extractKeyboard($this->builder->buildList($settings));

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
        return $this->builder->buildDetail($type, $settings)->getText()->getMessageText();
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
            $this->builder->buildDetail(NotificationType::BumpedFromGame, new NotificationSettings()),
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

        $keyboard = $this->extractKeyboard($this->builder->buildDetail(NotificationType::BumpedFromGame, $settings));

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
            $this->builder->buildDetail(NotificationType::BumpedFromGame, new NotificationSettings()),
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

    protected function setUp(): void
    {
        $this->builder = new NotificationsMessageBuilder(new Translator());
    }
}
