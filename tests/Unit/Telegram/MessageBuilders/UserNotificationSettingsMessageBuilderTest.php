<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\Admin\UserNotificationSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use PHPUnit\Framework\TestCase;

final class UserNotificationSettingsMessageBuilderTest extends TestCase
{
    use CreatesUserRecords;

    private const int USER_ID = 100;

    public function testListNamesTheUserUnderTheHeader(): void
    {
        $message = $this->builder()->buildList(new NotificationSettings());

        $this->assertSame(
            "*Notifications*\nUser: Alice\nChoose a notification to set it up\\.",
            $message->getText()->getMessageText(),
        );
    }

    public function testListLinksTheUsersUsername(): void
    {
        $builder = new UserNotificationSettingsMessageBuilder($this->userRecord(self::USER_ID, 'Alice', username: 'alice'));

        $this->assertStringContainsString(
            'User: [Alice](https://t.me/alice)',
            $builder->buildList(new NotificationSettings())->getText()->getMessageText(),
        );
    }

    public function testListStaysInEnglishWhateverTheUsersLanguage(): void
    {
        $builder = new UserNotificationSettingsMessageBuilder($this->userRecord(self::USER_ID, 'Alice', languageCode: 'ru'));

        $this->assertStringContainsString(
            'Choose a notification to set it up',
            $builder->buildList(new NotificationSettings())->getText()->getMessageText(),
        );
    }

    public function testListOpensEachTypeForTheUserAndMarksEnabledOnes(): void
    {
        $settings = new NotificationSettings()->enable(NotificationType::BumpedFromGame);

        $keyboard = $this->keyboard($this->builder()->buildList($settings));

        $this->assertCount(count(NotificationType::cases()) + 1, $keyboard);

        foreach (NotificationType::cases() as $index => $type) {
            $button = $keyboard[$index][0];
            $this->assertSame(sprintf('{"aa":"nd","u":100,"n":%d}', $type->value), $button['callback_data']);
            $this->assertSame(NotificationType::BumpedFromGame === $type, 'success' === ($button['style'] ?? null));
        }
    }

    public function testListGoesBackToTheUsersDetails(): void
    {
        $keyboard = $this->keyboard($this->builder()->buildList(new NotificationSettings()));

        $backButton = end($keyboard)[0];
        $this->assertSame("\u{21A9} Back", $backButton['text']);
        $this->assertSame('{"aa":"uv","u":100}', $backButton['callback_data']);
    }

    public function testDetailNamesTheUserUnderTheLabel(): void
    {
        $message = $this->builder()->buildDetail(NotificationType::GameReachedMinimumPlayers, new NotificationSettings());

        $this->assertStringStartsWith(
            "*✅ Game is on*\nUser: Alice\n\n🔕 You won't get a notification when",
            $message->getText()->getMessageText(),
        );
    }

    public function testDetailLinksTheUsersUsername(): void
    {
        $builder = new UserNotificationSettingsMessageBuilder($this->userRecord(self::USER_ID, 'Alice', username: 'alice'));

        $this->assertStringContainsString(
            "\nUser: [Alice](https://t.me/alice)\n\n",
            $builder->buildDetail(NotificationType::BumpedFromGame, new NotificationSettings())->getText()->getMessageText(),
        );
    }

    public function testDetailOfADisabledTypeOffersEnableForTheUser(): void
    {
        $keyboard = $this->keyboard(
            $this->builder()->buildDetail(NotificationType::PromotedIntoGame, new NotificationSettings()),
        );

        $this->assertSame('Enable', $keyboard[0][0]['text']);
        $this->assertSame('success', $keyboard[0][0]['style']);
        $this->assertSame('{"aa":"ne","u":100,"n":3}', $keyboard[0][0]['callback_data']);
        $this->assertSame('{"aa":"nl","u":100}', $keyboard[1][0]['callback_data']);
    }

    public function testDetailOfAnEnabledTypeOffersDisableForTheUser(): void
    {
        $settings = new NotificationSettings()->enable(NotificationType::PromotedIntoGame);

        $keyboard = $this->keyboard($this->builder()->buildDetail(NotificationType::PromotedIntoGame, $settings));

        $this->assertSame('Disable', $keyboard[0][0]['text']);
        $this->assertSame('danger', $keyboard[0][0]['style']);
        $this->assertSame('{"aa":"nx","u":100,"n":3}', $keyboard[0][0]['callback_data']);
    }

    private function builder(): UserNotificationSettingsMessageBuilder
    {
        return new UserNotificationSettingsMessageBuilder($this->userRecord(self::USER_ID, 'Alice'));
    }

    private function keyboard(TelegramMessage $message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }
}
