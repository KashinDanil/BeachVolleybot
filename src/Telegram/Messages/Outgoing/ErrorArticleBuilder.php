<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use TelegramBot\Api\Types\Inline\InputMessageContent\Text;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final readonly class ErrorArticleBuilder implements ArticleBuilderInterface
{
    private const string ARTICLE_ID = 'error';
    private const string DEFAULT_MESSAGE = "Use the following pattern to create a new game:```\n%s \nSaturday 11.04\nBogatell 10:00```";
    private const string PARSE_MODE = 'markdown';

    public function __construct(
        private InlineQueryError $error,
    ) {
    }

    public function build(): Article
    {
        return new Article(
            id: self::ARTICLE_ID,
            title: $this->error->title(),
            description: $this->error->description(),
            inputMessageContent: new Text(sprintf(self::DEFAULT_MESSAGE, BOT_USERNAME), self::PARSE_MODE),
        );
    }
}
