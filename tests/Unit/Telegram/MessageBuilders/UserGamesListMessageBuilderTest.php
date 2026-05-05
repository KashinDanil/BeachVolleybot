<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\KeyboardPagination;
use BeachVolleybot\Telegram\MessageBuilders\UserGamesListMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use PHPUnit\Framework\TestCase;

final class UserGamesListMessageBuilderTest extends TestCase
{
    private UserGamesListMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new UserGamesListMessageBuilder(new Translator());
    }

    // --- empty state ---

    public function testEmptyListShowsEmptyStateText(): void
    {
        $message = $this->builder->buildGamesList([], $this->paginationFor(0));

        $this->assertStringContainsString("You haven't created any games yet", $message->getText()->getMessageText());
    }

    public function testEmptyListShowsHeader(): void
    {
        $message = $this->builder->buildGamesList([], $this->paginationFor(0));

        $this->assertStringContainsString('Your games', $message->getText()->getMessageText());
    }

    public function testEmptyListHasEmptyKeyboard(): void
    {
        $message = $this->builder->buildGamesList([], $this->paginationFor(0));

        $this->assertSame([], $this->extractKeyboard($message));
    }

    // --- non-empty list ---

    public function testListShowsPageIndicator(): void
    {
        $games = $this->buildGameRows(count: 3);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(3));

        $this->assertStringContainsString('Page 1 of 1', $message->getText()->getMessageText());
    }

    public function testListShowsOneRowPerGame(): void
    {
        $games = $this->buildGameRows(count: 3);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(3));
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(3, $keyboard);
        $this->assertSame(1, count($keyboard[0]));
    }

    public function testGameButtonLabelIncludesGameId(): void
    {
        $games = [['game_id' => 42, 'title' => 'Friday Game 18:00']];

        $message = $this->builder->buildGamesList($games, $this->paginationFor(1));
        $keyboard = $this->extractKeyboard($message);

        $this->assertStringContainsString('#42', $keyboard[0][0]['text']);
    }

    public function testGameButtonCallbackContainsGameIdAndCurrentPage(): void
    {
        $games = [['game_id' => 42, 'title' => 'Friday Game 18:00']];

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 11, page: 2));
        $keyboard = $this->extractKeyboard($message);

        $callbackData = UserCallbackData::fromJson($keyboard[0][0]['callback_data']);
        $this->assertSame(UserCallbackAction::GameDetail, $callbackData->getAction());
        $this->assertSame(42, $callbackData->getGameId());
        $this->assertSame(2, $callbackData->getPage());
    }

    // --- pagination row ---

    public function testFirstPageHasOnlyNextButton(): void
    {
        $games = $this->buildGameRows(count: 5);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 11, page: 1));
        $keyboard = $this->extractKeyboard($message);

        $paginationRow = end($keyboard);
        $this->assertCount(1, $paginationRow);
        $this->assertStringContainsString('Next', $paginationRow[0]['text']);
    }

    public function testLastPageHasOnlyPrevButton(): void
    {
        $games = $this->buildGameRows(count: 1);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 11, page: 3));
        $keyboard = $this->extractKeyboard($message);

        $paginationRow = end($keyboard);
        $this->assertCount(1, $paginationRow);
        $this->assertStringContainsString('Prev', $paginationRow[0]['text']);
    }

    public function testMiddlePageHasBothPrevAndNext(): void
    {
        $games = $this->buildGameRows(count: 5);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 11, page: 2));
        $keyboard = $this->extractKeyboard($message);

        $paginationRow = end($keyboard);
        $this->assertCount(2, $paginationRow);
        $this->assertStringContainsString('Prev', $paginationRow[0]['text']);
        $this->assertStringContainsString('Next', $paginationRow[1]['text']);
    }

    public function testSinglePageHasNoPaginationRow(): void
    {
        $games = $this->buildGameRows(count: 3);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(3));
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(3, $keyboard);
        // No pagination row appended after the game rows
        $lastRow = end($keyboard);
        $this->assertCount(1, $lastRow); // Game row, not a multi-button pagination row
    }

    public function testPrevButtonCallbackTargetsPreviousPage(): void
    {
        $games = $this->buildGameRows(count: 5);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 15, page: 3));
        $keyboard = $this->extractKeyboard($message);
        $paginationRow = end($keyboard);

        $callbackData = UserCallbackData::fromJson($paginationRow[0]['callback_data']);
        $this->assertSame(UserCallbackAction::GamesList, $callbackData->getAction());
        $this->assertSame(2, $callbackData->getPage());
    }

    public function testNextButtonCallbackTargetsNextPage(): void
    {
        $games = $this->buildGameRows(count: 5);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 15, page: 1));
        $keyboard = $this->extractKeyboard($message);
        $paginationRow = end($keyboard);

        $callbackData = UserCallbackData::fromJson($paginationRow[0]['callback_data']);
        $this->assertSame(UserCallbackAction::GamesList, $callbackData->getAction());
        $this->assertSame(2, $callbackData->getPage());
    }

    // --- helpers ---

    private function paginationFor(int $totalGames, int $page = 1): KeyboardPagination
    {
        return new KeyboardPagination($totalGames, perPage: 5, page: $page);
    }

    /** @return list<array<string, mixed>> */
    private function buildGameRows(int $count): array
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['game_id' => $i, 'title' => "Game $i 18:00"];
        }

        return $rows;
    }

    private function extractKeyboard(TelegramMessage $message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }
}
