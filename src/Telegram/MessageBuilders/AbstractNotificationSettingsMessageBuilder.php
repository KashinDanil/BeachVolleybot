<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;

/** The notifications list and per-type screens; subclasses choose the buttons' callbacks. */
abstract class AbstractNotificationSettingsMessageBuilder extends AbstractMessageBuilder
{
    public const string HEADER_TEXT       = 'Notifications';
    public const string CHOOSE_TEXT       = 'Choose a notification to set it up.';
    public const string ENABLED_SENTENCE  = "🔔 You'll get a notification when %s.";
    public const string DISABLED_SENTENCE = "🔕 You won't get a notification when %s.";
    public const string LABEL_ENABLE      = 'Enable';
    public const string LABEL_DISABLE     = 'Disable';
    public const string ENABLED_TOAST     = 'Notification enabled';
    public const string DISABLED_TOAST    = 'Notification disabled';

    public function __construct(
        protected readonly Translator $translator,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
    }

    abstract protected function listCallbackData(): CallbackDataInterface;

    abstract protected function detailCallbackData(NotificationType $type): CallbackDataInterface;

    abstract protected function enableCallbackData(NotificationType $type): CallbackDataInterface;

    abstract protected function disableCallbackData(NotificationType $type): CallbackDataInterface;

    public function buildList(NotificationSettings $settings): TelegramMessage
    {
        $text = $this->buildListHeader()
            . $this->formatter->newLine()
            . $this->formatter->escape($this->translator->translate(self::CHOOSE_TEXT));

        return $this->buildMessage($text, $this->buildListKeyboard($settings));
    }

    protected function buildListHeader(): string
    {
        return $this->formatter->bold($this->translator->translate(self::HEADER_TEXT));
    }

    protected function buildListKeyboard(NotificationSettings $settings): array
    {
        $keyboard = [];

        foreach (NotificationType::cases() as $type) {
            $keyboard[] = [
                $this->buildActionButton(
                    new LocalizedNotificationTexts($type, $this->translator)->label(),
                    $this->detailCallbackData($type),
                    $settings->isEnabled($type) ? InlineButtonStyle::SUCCESS : null,
                ),
            ];
        }

        return $keyboard;
    }

    public function buildDetail(NotificationType $type, NotificationSettings $settings): TelegramMessage
    {
        $enabled = $settings->isEnabled($type);
        $texts = new LocalizedNotificationTexts($type, $this->translator);
        $newLine = $this->formatter->newLine();

        $text = $this->buildDetailHeader($texts)
            . $newLine
            . $newLine
            . $this->formatter->escape($this->buildStatusSentence($texts, $enabled));

        return $this->buildMessage($text, [
            [$this->buildSwitchButton($type, $enabled)],
            $this->backButtonRow($this->listCallbackData(), $this->translator->translate(self::LABEL_BACK)),
        ]);
    }

    protected function buildDetailHeader(LocalizedNotificationTexts $texts): string
    {
        return $this->formatter->bold($texts->label());
    }

    private function buildStatusSentence(LocalizedNotificationTexts $texts, bool $enabled): string
    {
        $sentence = $enabled ? self::ENABLED_SENTENCE : self::DISABLED_SENTENCE;

        return sprintf($this->translator->translate($sentence), $texts->trigger());
    }

    private function buildSwitchButton(NotificationType $type, bool $enabled): array
    {
        if ($enabled) {
            return $this->buildActionButton(
                $this->translator->translate(self::LABEL_DISABLE),
                $this->disableCallbackData($type),
                InlineButtonStyle::DANGER,
            );
        }

        return $this->buildActionButton(
            $this->translator->translate(self::LABEL_ENABLE),
            $this->enableCallbackData($type),
            InlineButtonStyle::SUCCESS,
        );
    }
}
