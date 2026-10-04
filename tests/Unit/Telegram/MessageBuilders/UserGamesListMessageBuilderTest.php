<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Game\UserGamesListMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\KeyboardPagination;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Tests\Fixtures\CreatesGameRecords;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;

final class UserGamesListMessageBuilderTest extends TestCase
{
    use CreatesGameRecords;

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
        $games = $this->buildGameRecords(count: 3);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(3));

        $this->assertStringContainsString('Page 1 of 1', $message->getText()->getMessageText());
    }

    public function testListShowsOneRowPerGame(): void
    {
        $games = $this->buildGameRecords(count: 3);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(3));
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(3, $keyboard);
        $this->assertSame(1, count($keyboard[0]));
    }

    public function testGameButtonLabelShowsIdAndKickoff(): void
    {
        $games = [$this->gameRecord(42)];

        $message = $this->builder->buildGamesList($games, $this->paginationFor(1));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('#42 · Thu, 31 Dec 2099 18:00', $keyboard[0][0]['text']);
    }

    public function testGameButtonLabelIsSpelledInTheUsersLanguage(): void
    {
        $games = [$this->gameRecord(42)];
        $builder = new UserGamesListMessageBuilder(new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_')));

        $message = $builder->buildGamesList($games, $this->paginationFor(1));
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('#42 · чт, 31 дек 2099 18:00', $keyboard[0][0]['text']);
    }

    public function testGameButtonCallbackContainsGameIdAndCurrentPage(): void
    {
        $games = [$this->gameRecord(42)];

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
        $games = $this->buildGameRecords(count: 5);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 11, page: 1));
        $keyboard = $this->extractKeyboard($message);

        $paginationRow = end($keyboard);
        $this->assertCount(1, $paginationRow);
        $this->assertStringContainsString('Next', $paginationRow[0]['text']);
    }

    public function testLastPageHasOnlyPrevButton(): void
    {
        $games = $this->buildGameRecords(count: 1);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 11, page: 3));
        $keyboard = $this->extractKeyboard($message);

        $paginationRow = end($keyboard);
        $this->assertCount(1, $paginationRow);
        $this->assertStringContainsString('Prev', $paginationRow[0]['text']);
    }

    public function testMiddlePageHasBothPrevAndNext(): void
    {
        $games = $this->buildGameRecords(count: 5);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 11, page: 2));
        $keyboard = $this->extractKeyboard($message);

        $paginationRow = end($keyboard);
        $this->assertCount(2, $paginationRow);
        $this->assertStringContainsString('Prev', $paginationRow[0]['text']);
        $this->assertStringContainsString('Next', $paginationRow[1]['text']);
    }

    public function testSinglePageHasNoPaginationRow(): void
    {
        $games = $this->buildGameRecords(count: 3);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(3));
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(3, $keyboard);
        // No pagination row appended after the game rows
        $lastRow = end($keyboard);
        $this->assertCount(1, $lastRow); // Game row, not a multi-button pagination row
    }

    public function testPrevButtonCallbackTargetsPreviousPage(): void
    {
        $games = $this->buildGameRecords(count: 5);

        $message = $this->builder->buildGamesList($games, $this->paginationFor(totalGames: 15, page: 3));
        $keyboard = $this->extractKeyboard($message);
        $paginationRow = end($keyboard);

        $callbackData = UserCallbackData::fromJson($paginationRow[0]['callback_data']);
        $this->assertSame(UserCallbackAction::GamesList, $callbackData->getAction());
        $this->assertSame(2, $callbackData->getPage());
    }

    public function testNextButtonCallbackTargetsNextPage(): void
    {
        $games = $this->buildGameRecords(count: 5);

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

    /** @return list<GameRecord> */
    private function buildGameRecords(int $count): array
    {
        $gameRecords = [];

        for ($gameId = 1; $gameId <= $count; $gameId++) {
            $gameRecords[] = $this->gameRecord($gameId);
        }

        return $gameRecords;
    }

    private function extractKeyboard(TelegramMessage $message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }
}
