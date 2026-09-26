<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;

final class NotificationsMessageBuilder extends AbstractMessageBuilder
{
    public const string HEADER_TEXT       = 'Notifications';
    public const string CHOOSE_TEXT       = 'Choose a notification to set it up.';
    public const string ENABLED_SENTENCE  = "🔔 You'll get a notification when %s.";
    public const string DISABLED_SENTENCE = "🔕 You won't get a notification when %s.";
    public const string LABEL_ENABLE      = 'Enable';
    public const string LABEL_DISABLE     = 'Disable';
    public const string ENABLED_TOAST     = 'Notification enabled';
    public const string DISABLED_TOAST    = 'Notification disabled';

    public const string GAME_REACHED_MINIMUM_PLAYERS_LABEL   = '✅ Game is on';
    public const string GAME_REACHED_MINIMUM_PLAYERS_TRIGGER = "a game you've joined gets enough players to take place";
    public const string GAME_SHORT_BEFORE_KICKOFF_LABEL      = '⚠️ Short of players';
    public const string GAME_SHORT_BEFORE_KICKOFF_TRIGGER    = "kickoff is near and a game you've joined still doesn't have enough players";
    public const string PROMOTED_INTO_GAME_LABEL             = "⬆️ You're in";
    public const string PROMOTED_INTO_GAME_TRIGGER           = 'a spot opens up and you move from the reserve to playing';
    public const string BUMPED_FROM_GAME_LABEL               = "⬇️ You're out";
    public const string BUMPED_FROM_GAME_TRIGGER             = 'you move from playing to the reserve, for example after a net or volleyball is removed';

    public function __construct(
        private readonly Translator $translator,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
    }

    public function buildList(NotificationSettings $settings): TelegramMessage
    {
        $text = $this->formatter->bold($this->translator->translate(self::HEADER_TEXT))
            . $this->formatter->newLine()
            . $this->formatter->escape($this->translator->translate(self::CHOOSE_TEXT));

        return $this->buildMessage($text, $this->buildListKeyboard($settings));
    }

    private function buildListKeyboard(NotificationSettings $settings): array
    {
        $keyboard = [];

        foreach (NotificationType::cases() as $type) {
            $keyboard[] = [
                $this->buildActionButton(
                    $this->translator->translate($this->label($type)),
                    UserCallbackData::create(UserCallbackAction::NotificationDetail)->withNotificationType($type),
                    $settings->isEnabled($type) ? InlineButtonStyle::SUCCESS : null,
                ),
            ];
        }

        return $keyboard;
    }

    private function label(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::GameReachedMinimumPlayers => self::GAME_REACHED_MINIMUM_PLAYERS_LABEL,
            NotificationType::GameShortBeforeKickoff => self::GAME_SHORT_BEFORE_KICKOFF_LABEL,
            NotificationType::PromotedIntoGame => self::PROMOTED_INTO_GAME_LABEL,
            NotificationType::BumpedFromGame => self::BUMPED_FROM_GAME_LABEL,
        };
    }

    public function buildDetail(NotificationType $type, NotificationSettings $settings): TelegramMessage
    {
        $enabled = $settings->isEnabled($type);
        $newLine = $this->formatter->newLine();

        $text = $this->formatter->bold($this->translator->translate($this->label($type)))
            . $newLine
            . $newLine
            . $this->formatter->escape($this->buildStatusSentence($type, $enabled));

        return $this->buildMessage($text, [
            [$this->buildSwitchButton($type, $enabled)],
            $this->backButtonRow(
                UserCallbackData::create(UserCallbackAction::NotificationsList),
                $this->translator->translate(self::LABEL_BACK),
            ),
        ]);
    }

    private function buildStatusSentence(NotificationType $type, bool $enabled): string
    {
        $sentence = $enabled ? self::ENABLED_SENTENCE : self::DISABLED_SENTENCE;

        return sprintf(
            $this->translator->translate($sentence),
            $this->translator->translate($this->trigger($type)),
        );
    }

    private function trigger(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::GameReachedMinimumPlayers => self::GAME_REACHED_MINIMUM_PLAYERS_TRIGGER,
            NotificationType::GameShortBeforeKickoff => self::GAME_SHORT_BEFORE_KICKOFF_TRIGGER,
            NotificationType::PromotedIntoGame => self::PROMOTED_INTO_GAME_TRIGGER,
            NotificationType::BumpedFromGame => self::BUMPED_FROM_GAME_TRIGGER,
        };
    }

    private function buildSwitchButton(NotificationType $type, bool $enabled): array
    {
        if ($enabled) {
            return $this->buildActionButton(
                $this->translator->translate(self::LABEL_DISABLE),
                UserCallbackData::create(UserCallbackAction::DisableNotification)->withNotificationType($type),
                InlineButtonStyle::DANGER,
            );
        }

        return $this->buildActionButton(
            $this->translator->translate(self::LABEL_ENABLE),
            UserCallbackData::create(UserCallbackAction::EnableNotification)->withNotificationType($type),
            InlineButtonStyle::SUCCESS,
        );
    }
}
