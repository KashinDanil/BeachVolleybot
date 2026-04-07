<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final readonly class ErrorArticleBuilder implements ArticleBuilderInterface
{
    private const string ARTICLE_ID = 'error';
    private const string DEFAULT_MESSAGE = 'Use the following pattern to create a new game:';
    private const string DEFAULT_MESSAGE_EXAMPLE = "@%s \nSaturday 11.04\nBogatell 10:00";

    public function __construct(
        private InlineQueryError $error,
        private Translator $translator,
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
        $text = $this->formatter->escape($this->translator->translate(self::DEFAULT_MESSAGE));
        $example = $this->formatter->codeBlock(sprintf($this->translator->translate(self::DEFAULT_MESSAGE_EXAMPLE), BOT_USERNAME));

        return $text . "\n" . $example;
    }
}
