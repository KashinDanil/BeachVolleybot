<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

/**
 * @method string buildShareText()
 * @method string buildButtonText()
 * @method array  buildShareKeyboard(int $gameId)
 */
final class ShareGameMessageBuilder extends AbstractMessageBuilder
{
    public const string SHARE_TEXT      = 'Share this game to another chat';
    public const string BUTTON_TEXT     = 'Share';
    public const string DISABLED_NOTICE = '🏁 This game has finished and can no longer be shared';

    public function __construct(
        private readonly Translator $translator,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
    }

    public function build(int $gameId): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildShareText(),
            $this->buildShareKeyboard($gameId),
        );
    }

    public static function switchQuery(int $gameId): string
    {
        return ForwardGameQueryExtractor::PREFIX . ' ' . $gameId;
    }

    public static function renderDisabledNotice(
        MessageFormatterInterface $formatter,
        ?Translator $translator = null,
    ): string {
        $text = $translator?->translate(self::DISABLED_NOTICE) ?? self::DISABLED_NOTICE;

        return $formatter->blockquote($formatter->escape($text));
    }

    protected function defaultBuildShareText(): string
    {
        return $this->formatter->escape($this->translator->translate(self::SHARE_TEXT));
    }

    protected function defaultBuildButtonText(): string
    {
        return $this->translator->translate(self::BUTTON_TEXT);
    }

    protected function defaultBuildShareKeyboard(int $gameId): array
    {
        return [
            [
                $this->buildSwitchInlineQueryButton(
                    $this->buildButtonText(),
                    self::switchQuery($gameId),
                ),
            ],
        ];
    }
}
