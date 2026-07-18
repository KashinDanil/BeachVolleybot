<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\InlineQueryProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\Outgoing\InlineQueryError;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class InlineQueryProcessorTest extends ProcessorTestCase
{
    public function testValidQueryAnswersInlineQuery(): void
    {
        $update = $this->buildUpdate('query_1', 'Saturday Beach Game 18:00');

        new InlineQueryProcessor($this->telegramSender)->process($update);

        $this->assertInlineQueryAnswered();
    }

    public function testInvalidQueryAnswersWithErrorArticle(): void
    {
        $update = $this->buildUpdate('query_1', 'Beach Game');

        new InlineQueryProcessor($this->telegramSender)->process($update);

        $this->assertInlineQueryAnswered();
        $call = $this->lastInlineQueryCall();
        $this->assertSame(InlineQueryError::DATE_AND_TIME_NOT_FOUND_TITLE, $call['args'][1][0]->getTitle());
    }

    public function testPastKickoffDayAnswersWithErrorArticle(): void
    {
        $update = $this->buildUpdate('query_1', 'Beach 01.01.20 18:00');

        new InlineQueryProcessor($this->telegramSender)->process($update);

        $this->assertInlineQueryAnswered();
        $call = $this->lastInlineQueryCall();
        $this->assertSame(InlineQueryError::KICKOFF_DAY_IN_THE_PAST_TITLE, $call['args'][1][0]->getTitle());
    }

    public function testForwardQueryByCreatorAnswersWithForwardArticle(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 200);
        $update = $this->buildUpdate('query_42', "Forward game $gameId");

        new InlineQueryProcessor($this->telegramSender)->process($update);

        $this->assertInlineQueryAnswered();
        $article = $this->lastInlineQueryCall()['args'][1][0];
        $this->assertSame('query_42', $article->getId());
        $this->assertStringStartsWith('🏐 Game', $article->getTitle());
    }

    public function testForwardQueryByNonCreatorAnswersWithGameNotFoundError(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 100);
        $update = $this->buildUpdate('query_1', "Forward game $gameId");

        new InlineQueryProcessor($this->telegramSender)->process($update);

        $this->assertInlineQueryAnswered();
        $article = $this->lastInlineQueryCall()['args'][1][0];
        $this->assertSame(InlineQueryError::GAME_NOT_FOUND_TITLE, $article->getTitle());
    }

    public function testForwardQueryByAdminAnswersWithForwardArticleEvenWhenNotCreator(): void
    {
        $this->seedAdmin();
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 100);
        $update = TelegramUpdate::fromArray(
            $this->inlineQueryPayload('query_admin', "Forward game $gameId", fromId: self::ADMIN_TELEGRAM_USER_ID),
        );

        new InlineQueryProcessor($this->telegramSender)->process($update);

        $this->assertInlineQueryAnswered();
        $article = $this->lastInlineQueryCall()['args'][1][0];
        $this->assertSame('query_admin', $article->getId());
        $this->assertStringStartsWith('🏐 Game', $article->getTitle());
    }

    public function testForwardQueryForFinishedGameAnswersWithGameFinishedError(): void
    {
        $gameId = $this->createGame(title: 'Saturday 01.01.2020 18:00', createdBy: 200);
        $update = $this->buildUpdate('query_finished', "Forward game $gameId");

        new InlineQueryProcessor($this->telegramSender)->process($update);

        $this->assertInlineQueryAnswered();
        $article = $this->lastInlineQueryCall()['args'][1][0];
        $this->assertSame(InlineQueryError::GAME_FINISHED_TITLE, $article->getTitle());
    }

    public function testForwardQueryForNonExistentGameAnswersWithGameNotFoundError(): void
    {
        $update = $this->buildUpdate('query_1', 'Forward game 9999');

        new InlineQueryProcessor($this->telegramSender)->process($update);

        $this->assertInlineQueryAnswered();
        $article = $this->lastInlineQueryCall()['args'][1][0];
        $this->assertSame(InlineQueryError::GAME_NOT_FOUND_TITLE, $article->getTitle());
    }

    private function buildUpdate(string $inlineQueryId, string $query): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->inlineQueryPayload($inlineQueryId, $query),
        );
    }

    private function assertInlineQueryAnswered(): void
    {
        $calls = array_filter($this->bot->calls, fn($call) => 'answerInlineQuery' === $call['method']);
        $this->assertNotEmpty($calls, 'Expected answerInlineQuery to be called');
    }

    private function lastInlineQueryCall(): array
    {
        $calls = array_filter($this->bot->calls, fn($call) => 'answerInlineQuery' === $call['method']);
        $this->assertNotEmpty($calls, 'Expected answerInlineQuery to be called');

        return end($calls);
    }
}
