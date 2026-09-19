<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use BeachVolleybot\Common\Command;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final readonly class ErrorArticleBuilder implements ArticleBuilderInterface
{
    private const string ARTICLE_ID = 'error';
    private const string PATTERN_INTRO = 'Use the following pattern to create a new game:';
    private const string PATTERN_EXAMPLE = "@%s \n📅 Saturday\n🕙 10:00\n🏖️ Bogatell";
    private const string PRIVATE_CHAT_GUIDANCE = "Or use the %s command and I'll help you create a game.";
    private const string GROUP_CHAT_GUIDANCE = 'Or type %s to open the commands menu and start a new game from there.';

    public function __construct(
        private InlineQueryError $error,
        private Translator $translator,
        private bool $isGroupChat,
        private MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
    }

    public function build(): Article
    {
        return new Article(
            id: self::ARTICLE_ID,
            title: $this->translator->translate($this->error->title()),
            description: $this->translator->translate($this->error->description()),
            inputMessageContent: new Text($this->buildDefaultMessage(), $this->formatter->parseMode()),
        );
    }

    private function buildDefaultMessage(): string
    {
        $newLine = $this->formatter->newLine();

        return $this->buildPatternIntro()
            . $newLine
            . $this->buildPatternExample()
            . $newLine
            . $this->buildGuidance();
    }

    private function buildPatternIntro(): string
    {
        return $this->formatter->escape($this->translator->translate(self::PATTERN_INTRO));
    }

    private function buildPatternExample(): string
    {
        return $this->formatter->codeBlock(sprintf($this->translator->translate(self::PATTERN_EXAMPLE), BOT_USERNAME));
    }

    private function buildGuidance(): string
    {
        return $this->isGroupChat ? $this->buildGroupGuidance() : $this->buildPrivateGuidance();
    }

    private function buildPrivateGuidance(): string
    {
        return $this->formatter->escape(sprintf($this->translator->translate(self::PRIVATE_CHAT_GUIDANCE), Command::NewGame->value));
    }

    /**
     * The slash is rendered as inline code (not escaped like the rest of the
     * sentence) so it stays monospaced and copyable — typing it is what opens
     * Telegram's own commands menu, which is what keeps the resulting command
     * ephemeral in a group instead of a plain, publicly-visible message.
     */
    private function buildGroupGuidance(): string
    {
        [$before, $after] = explode('%s', $this->translator->translate(self::GROUP_CHAT_GUIDANCE), 2);

        return $this->formatter->escape($before) . $this->formatter->code('/') . $this->formatter->escape($after);
    }
}
