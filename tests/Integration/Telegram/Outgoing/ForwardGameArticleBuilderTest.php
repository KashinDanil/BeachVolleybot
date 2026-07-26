<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Telegram\Outgoing;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineQuery;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Telegram\Messages\Outgoing\ForwardGameArticleBuilder;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use DanilKashin\Localization\Language;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final class ForwardGameArticleBuilderTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testArticleIdMatchesInlineQueryId(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00');

        $article = $this->buildArticle($gameId, 'Saturday 18:00', gameKey: 'query_42');

        $this->assertSame('query_42', $article->getId());
    }

    public function testArticleTitleIncludesLocalizedPrefixAndGameLabel(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00');

        $article = $this->buildArticle($gameId, 'Saturday 18:00');

        $this->assertSame("🏐 Game #$gameId Saturday 18:00", $article->getTitle());
    }

    public function testArticleTitleIsTranslated(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00');

        $article = $this->buildArticle($gameId, 'Saturday 18:00', language: Language::RU);

        $this->assertStringStartsWith('🏐 Игра ', $article->getTitle());
    }

    public function testArticleDescriptionIsTranslated(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00');

        $article = $this->buildArticle($gameId, 'Saturday 18:00', language: Language::ES);

        $this->assertSame('Toca para enviar este partido', $article->getDescription());
    }

    public function testArticleHasInputMessageContent(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00');

        $article = $this->buildArticle($gameId, 'Saturday 18:00');

        $this->assertNotNull($article->getInputMessageContent());
    }

    public function testInputMessageContentReflectsGameTitle(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00');

        $article = $this->buildArticle($gameId, 'Saturday 18:00');

        $this->assertStringContainsString('Saturday 18:00', $article->getInputMessageContent()->getMessageText());
    }

    private function buildArticle(
        int $gameId,
        string $gameTitle,
        string $gameKey = 'query_1',
        string $language = Language::EN,
    ): Article {
        $inlineQuery = new TelegramInlineQuery(
            id: $gameKey,
            from: new TelegramUser(id: 100, firstName: 'Alice'),
            query: "Forward game $gameId",
            offset: '',
        );
        $translator = new Translator($language, tempnam(sys_get_temp_dir(), 'bvb_missing_'));

        $builder = new ForwardGameArticleBuilder($inlineQuery, $gameId, $gameTitle, $translator);

        return $builder->build();
    }
}
