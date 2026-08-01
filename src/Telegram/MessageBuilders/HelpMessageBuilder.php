<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Common\Command;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class HelpMessageBuilder extends AbstractMessageBuilder
{
    public const string CREATE_PARAGRAPH       = '**To create a game**, add **@%s** to the start of the message. The text can be anything — just include a **date and time** 📆🕙';
    public const string EXAMPLE_LABEL          = 'Example:';
    public const string EXAMPLE_TEMPLATE       = "@%s \n📅 Saturday\n🕙 10:00\n🏖️ Bogatell";
    public const string AFTER_EXAMPLE          = "Then just tap the bot's hint to send the message.";
    public const string WIZARD_PARAGRAPH       = "**Or use the %s command** and I'll help you create a game.";
    public const string JOIN_LEAVE_PARAGRAPH   = '**To join a game or leave it**, use the buttons under the message.';
    public const string LATE_PARAGRAPH         = '**To join at another time**, reply to the game message with just the time, like **17:30**.';
    public const string LOCATION_PARAGRAPH     = '**To add a location** 📍, reply to the game message with a geo point.';
    public const string CHANGE_TITLE_PARAGRAPH = '**To change the game**, reply to the game message with the new text — just keep a date and time in it. Only the person who created the game can do this.';
    public const string GEAR_PARAGRAPH         = '**Volleyballs and nets** can also be marked with the buttons under the game: +🏐 / −🏐 and +🕸 / −🕸. If something is missing, the bot will show it.';
    public const string GAMES_LIST_PARAGRAPH   = '**To share a game across multiple chats**, use the %s command in my DM — the game stays in sync everywhere.';

    public function __construct(
        private readonly Translator $translator,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
    }

    public function build(string $botUsername, bool $isGroupChat): TelegramMessage
    {
        return $this->buildMessage($this->buildText($botUsername, $isGroupChat), []);
    }

    private function buildText(string $botUsername, bool $isGroupChat): string
    {
        $newLine = $this->formatter->newLine();
        $blank = $newLine . $newLine;
        $newGameCommand = $isGroupChat ? Command::NewGame->mention() : Command::NewGame->value;

        return $this->renderParagraph(sprintf($this->translator->translate(self::CREATE_PARAGRAPH), $botUsername))
            . $blank
            . $this->formatter->escape($this->translator->translate(self::EXAMPLE_LABEL))
            . $newLine
            . $this->formatter->codeBlock(sprintf($this->translator->translate(self::EXAMPLE_TEMPLATE), $botUsername))
            . $newLine
            . $this->formatter->escape($this->translator->translate(self::AFTER_EXAMPLE))
            . $blank
            . $this->renderParagraph(sprintf($this->translator->translate(self::WIZARD_PARAGRAPH), $newGameCommand))
            . $blank
            . $this->renderParagraph($this->translator->translate(self::JOIN_LEAVE_PARAGRAPH))
            . $blank
            . $this->renderParagraph($this->translator->translate(self::LATE_PARAGRAPH))
            . $blank
            . $this->renderParagraph($this->translator->translate(self::LOCATION_PARAGRAPH))
            . $blank
            . $this->renderParagraph($this->translator->translate(self::CHANGE_TITLE_PARAGRAPH))
            . $blank
            . $this->renderParagraph($this->translator->translate(self::GEAR_PARAGRAPH))
            . $blank
            . $this->renderParagraph(sprintf($this->translator->translate(self::GAMES_LIST_PARAGRAPH), Command::Games->value));
    }

    /**
     * Translation strings carry inline `**bold**` markers so a translator can
     * keep the bolded phrase aligned with the surrounding sentence in their
     * own language. We split on the markers, escape plain segments, and route
     * the bolded segments through `bold()` (which escapes them in turn).
     */
    private function renderParagraph(string $paragraph): string
    {
        $output = '';
        $segments = preg_split('/(\*\*[^*]+\*\*)/u', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($segments as $segment) {
            if (1 === preg_match('/^\*\*(.+)\*\*$/u', $segment, $match)) {
                $output .= $this->formatter->bold($match[1]);
            } else {
                $output .= $this->formatter->escape($segment);
            }
        }

        return $output;
    }
}
