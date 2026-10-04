<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Admin;

use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\MessageBuilders\AbstractNotificationSettingsMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\LocalizedNotificationTexts;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserRecord;

/** The user's own notification screens, as root sees them for that user; the admin panel stays in English. */
final class UserNotificationSettingsMessageBuilder extends AbstractNotificationSettingsMessageBuilder
{
    private const string USER_LABEL = 'User: ';

    public function __construct(private readonly UserRecord $user)
    {
        parent::__construct(new Translator());
    }

    protected function buildListHeader(): string
    {
        return parent::buildListHeader() . $this->formatter->newLine() . $this->buildUserLine();
    }

    protected function buildDetailHeader(LocalizedNotificationTexts $texts): string
    {
        return parent::buildDetailHeader($texts) . $this->formatter->newLine() . $this->buildUserLine();
    }

    private function buildUserLine(): string
    {
        return $this->formatter->escape(self::USER_LABEL) . $this->formatUserName();
    }

    private function formatUserName(): string
    {
        $userName = Player::buildName($this->user->firstName, $this->user->lastName);
        $userLink = Player::buildLink($this->user->username);

        if (null === $userLink) {
            return $this->formatter->escape($userName);
        }

        return $this->formatter->link($userName, $userLink);
    }

    protected function buildListKeyboard(NotificationSettings $settings): array
    {
        $keyboard = parent::buildListKeyboard($settings);
        $keyboard[] = $this->backButtonRow($this->userCallbackData(AdminCallbackAction::UserDetail));

        return $keyboard;
    }

    protected function listCallbackData(): CallbackDataInterface
    {
        return $this->userCallbackData(AdminCallbackAction::UserNotifications);
    }

    protected function detailCallbackData(NotificationType $type): CallbackDataInterface
    {
        return $this->userCallbackData(AdminCallbackAction::UserNotificationDetail)->withNotificationType($type);
    }

    protected function enableCallbackData(NotificationType $type): CallbackDataInterface
    {
        return $this->userCallbackData(AdminCallbackAction::EnableUserNotification)->withNotificationType($type);
    }

    protected function disableCallbackData(NotificationType $type): CallbackDataInterface
    {
        return $this->userCallbackData(AdminCallbackAction::DisableUserNotification)->withNotificationType($type);
    }

    private function userCallbackData(AdminCallbackAction $action): AdminCallbackData
    {
        return AdminCallbackData::create($action)->withUserId($this->user->telegramUserId);
    }
}
