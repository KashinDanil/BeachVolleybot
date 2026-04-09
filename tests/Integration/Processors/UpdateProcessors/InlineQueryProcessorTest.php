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
