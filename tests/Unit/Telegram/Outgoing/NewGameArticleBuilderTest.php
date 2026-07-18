<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\Outgoing;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineQuery;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Telegram\Messages\Outgoing\NewGameArticleBuilder;
use PHPUnit\Framework\TestCase;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final class NewGameArticleBuilderTest extends TestCase
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

        $this->assertSame('🏐 New game', $article->getTitle());
    }

    public function testArticleDescription(): void
    {
        $article = $this->buildArticle(query: 'Sunday Game 18:00');

        $this->assertSame('Tap to create a new game', $article->getDescription());
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

    // --- User built from inline query user ---

    public function testUserNameWithFirstAndLastName(): void
    {
        $article = $this->buildArticle(firstName: 'Alice', lastName: 'Smith');

        $text = $article->getInputMessageContent()->getMessageText();

        $this->assertStringContainsString('Alice Smith', $text);
    }

    public function testUserNameWithFirstNameOnly(): void
    {
        $article = $this->buildArticle(firstName: 'Alice', lastName: 'Smith');
        $textWithLastName = $article->getInputMessageContent()->getMessageText();

        $article = $this->buildArticle(firstName: 'Alice');
        $text = $article->getInputMessageContent()->getMessageText();

        $this->assertStringContainsString('Alice Smith', $textWithLastName);
        $this->assertStringContainsString('Alice', $text);
        $this->assertStringNotContainsString('Alice Smith', $text);
    }

    public function testUserLinkBuiltFromUsername(): void
    {
        $article = $this->buildArticle(firstName: 'Alice', username: 'alice');

        $text = $article->getInputMessageContent()->getMessageText();

        $this->assertStringContainsString('https://t.me/alice', $text);
    }

    public function testUserLinkNullWhenUsernameNull(): void
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

        return (new NewGameArticleBuilder($inlineQuery, new Translator()))->build();
    }
}
