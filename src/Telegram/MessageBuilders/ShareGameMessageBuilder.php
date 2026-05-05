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
 * @method string buildSwitchQuery(int $gameId)
 * @method array  buildShareKeyboard(int $gameId)
 */
final class ShareGameMessageBuilder extends AbstractMessageBuilder
{
    public const string SHARE_TEXT  = 'Share this game to another chat';
    public const string BUTTON_TEXT = 'Share';

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

    protected function defaultBuildShareText(): string
    {
        return $this->formatter->escape($this->translator->translate(self::SHARE_TEXT));
    }

    protected function defaultBuildButtonText(): string
    {
        return $this->translator->translate(self::BUTTON_TEXT);
    }

    protected function defaultBuildSwitchQuery(int $gameId): string
    {
        return ForwardGameQueryExtractor::PREFIX . ' ' . $gameId;
    }

    protected function defaultBuildShareKeyboard(int $gameId): array
    {
        return [
            [
                $this->buildSwitchInlineQueryButton(
                    $this->buildButtonText(),
                    $this->buildSwitchQuery($gameId),
                ),
            ],
        ];
    }
}
