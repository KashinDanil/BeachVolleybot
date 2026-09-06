<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Telegram\Outgoing;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineQuery;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Telegram\Messages\Outgoing\ForwardGameArticleBuilder;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use DanilKashin\Localization\Language;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final class ForwardGameArticleBuilderTest extends DatabaseTestCase
{
    private const string TITLE = 'Beach 31.12.2099 18:00';
    private const string LABEL = '· Thu, 31 Dec 2099 18:00';

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
        $gameId = $this->createGame(title: self::TITLE);

        $article = $this->buildArticle($gameId, gameKey: 'query_42');

        $this->assertSame('query_42', $article->getId());
    }

    public function testArticleTitleIncludesLocalizedPrefixAndGameLabel(): void
    {
        $gameId = $this->createGame(title: self::TITLE);

        $article = $this->buildArticle($gameId);

        $this->assertSame("🏐 Game #$gameId " . self::LABEL, $article->getTitle());
    }

    public function testArticleTitleIsTranslated(): void
    {
        $gameId = $this->createGame(title: self::TITLE);

        $article = $this->buildArticle($gameId, language: Language::RU);

        $this->assertStringStartsWith('🏐 Игра ', $article->getTitle());
    }

    public function testArticleDescriptionIsTranslated(): void
    {
        $gameId = $this->createGame(title: self::TITLE);

        $article = $this->buildArticle($gameId, language: Language::ES);

        $this->assertSame('Toca para enviar este partido', $article->getDescription());
    }

    public function testArticleHasInputMessageContent(): void
    {
        $gameId = $this->createGame(title: self::TITLE);

        $article = $this->buildArticle($gameId);

        $this->assertNotNull($article->getInputMessageContent());
    }

    public function testInputMessageContentReflectsGameTitle(): void
    {
        $gameId = $this->createGame(title: self::TITLE);

        $article = $this->buildArticle($gameId);

        $this->assertStringContainsString(
            new MarkdownV2()->escape(self::TITLE),
            $article->getInputMessageContent()->getMessageText(),
        );
    }

    /** The processor already read the games row to authorize the forward; the article reuses it. */
    public function testBuildsWithoutRereadingTheGameRow(): void
    {
        $gameId = $this->createGame(title: self::TITLE);
        $gameRecord = new GameManager()->findGameRecordById($gameId);

        $queries = $this->queriesDuring(function () use ($gameId, $gameRecord) {
            new ForwardGameArticleBuilder(
                $this->inlineQuery($gameId, 'query_1'),
                $gameRecord,
                new Translator(),
            )->build();
        });

        $gameReads = array_filter(
            $queries,
            static fn(string $query): bool => str_starts_with($query, 'SELECT') && str_contains($query, '"games"'),
        );

        $this->assertSame([], $gameReads);
    }

    private function inlineQuery(int $gameId, string $gameKey): TelegramInlineQuery
    {
        return new TelegramInlineQuery(
            id: $gameKey,
            from: new TelegramUser(id: 100, firstName: 'Alice'),
            query: "Forward game $gameId",
            offset: '',
        );
    }

    private function buildArticle(
        int $gameId,
        string $gameKey = 'query_1',
        string $language = Language::EN,
    ): Article {
        $inlineQuery = $this->inlineQuery($gameId, $gameKey);
        $translator = new Translator($language, tempnam(sys_get_temp_dir(), 'bvb_missing_'));

        $gameRecord = new GameManager()->findGameRecordById($gameId);

        return new ForwardGameArticleBuilder($inlineQuery, $gameRecord, $translator)->build();
    }
}
