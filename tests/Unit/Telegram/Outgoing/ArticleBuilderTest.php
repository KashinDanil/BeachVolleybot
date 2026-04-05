<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\Outgoing;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineQuery;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Telegram\Messages\Outgoing\ArticleBuilder;
use PHPUnit\Framework\TestCase;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final class ArticleBuilderTest extends TestCase
{
    // --- Article-level properties ---

    public function testArticleId(): void
    {
        $article = $this->buildArticle(inlineQueryId: 'query_42');

        $this->assertSame('query_42', $article->getId());
    }

    public function testArticleTitle(): void
    {
        $article = $this->buildArticle();

        $this->assertSame('Create new game', $article->getTitle());
    }

    public function testArticleDescription(): void
    {
        $article = $this->buildArticle(query: 'Sunday Game 18:00');

        $this->assertSame('Sunday Game 18:00', $article->getDescription());
    }

    // --- Article content ---

    public function testArticleHasInputMessageContent(): void
    {
        $article = $this->buildArticle();

        $this->assertNotNull($article->getInputMessageContent());
    }

    public function testArticleHasKeyboard(): void
    {
        $article = $this->buildArticle();

        $this->assertNotNull($article->getReplyMarkup());
    }

    // --- Player built from inline query user ---

    public function testPlayerNameWithFirstAndLastName(): void
    {
        $article = $this->buildArticle(firstName: 'Alice', lastName: 'Smith');

        $text = $article->getInputMessageContent()->getMessageText();

        $this->assertStringContainsString('Alice Smith', $text);
    }

    public function testPlayerNameWithFirstNameOnly(): void
    {
        $article = $this->buildArticle(firstName: 'Alice', lastName: 'Smith');
        $textWithLastName = $article->getInputMessageContent()->getMessageText();

        $article = $this->buildArticle(firstName: 'Alice');
        $text = $article->getInputMessageContent()->getMessageText();

        $this->assertStringContainsString('Alice Smith', $textWithLastName);
        $this->assertStringContainsString('Alice', $text);
        $this->assertStringNotContainsString('Alice Smith', $text);
    }

    public function testPlayerLinkBuiltFromUsername(): void
    {
        $article = $this->buildArticle(firstName: 'Alice', username: 'alice');

        $text = $article->getInputMessageContent()->getMessageText();

        $this->assertStringContainsString('https://t.me/alice', $text);
    }

    public function testPlayerLinkNullWhenUsernameNull(): void
    {
        $article = $this->buildArticle(firstName: 'Alice');

        $text = $article->getInputMessageContent()->getMessageText();

        $this->assertStringNotContainsString('https://t.me/', $text);
    }

    // --- Helpers ---

    private function buildArticle(
        string $inlineQueryId = 'query_1',
        string $query = 'Beach Game 18:00',
        string $firstName = 'Alice',
        ?string $lastName = null,
        ?string $username = null,
    ): Article {
        $inlineQuery = new TelegramInlineQuery(
            id: $inlineQueryId,
            from: new TelegramUser(
                id: 100,
                firstName: $firstName,
                lastName: $lastName,
                username: $username,
            ),
            query: $query,
            offset: '',
        );

        return (new ArticleBuilder($inlineQuery))->build();
    }
}
