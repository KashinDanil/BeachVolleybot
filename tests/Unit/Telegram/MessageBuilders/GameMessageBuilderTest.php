<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Telegram\MessageBuilders\GamesListMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\KeyboardPagination;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;

final class GameMessageBuilderTest extends DatabaseTestCase
{
    private const int GAMES_PER_PAGE = 5;

    private GamesListMessageBuilder $gamesListViewBuilder;

    private GameRepository $gameRepository;

    public function testGameButtonShowsIdWeekdayDateAndTime(): void
    {
        $this->createGame(title: 'Beach 31.12.2099 18:00');

        $message = $this->buildGamesList();
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('#1 · Thu, 31 Dec 2099 18:00', $keyboard[0][0]['text']);
    }

    private function buildGamesList(int $page = 1): TelegramMessage
    {
        $totalGames = $this->gameRepository->countAll();
        $pagination = new KeyboardPagination($totalGames, self::GAMES_PER_PAGE, $page);
        $games = $this->gameRepository->findAllDescending(self::GAMES_PER_PAGE, 0);

        return $this->gamesListViewBuilder->buildGamesList($games, $pagination);
    }

    // --- buildGamesList label format ---

    private function extractKeyboard($message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }

    public function testGameButtonReadsTheStoredKickoffRatherThanTheTitle(): void
    {
        $this->createGame(title: 'Beach 12.04.2020 10:00', kickoffAt: '2099-12-31 18:00:00');

        $message = $this->buildGamesList();
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('#1 · Thu, 31 Dec 2099 18:00', $keyboard[0][0]['text']);
    }

    public function testGamesListOrdersDescendingById(): void
    {
        $this->createGame(title: 'First 01.01.2020 10:00', inlineMessageId: 'msg_1', gameKey: 'q_1');
        $this->createGame(title: 'Second 01.01.2020 14:00', inlineMessageId: 'msg_2', gameKey: 'q_2');

        $message = $this->buildGamesList();
        $keyboard = $this->extractKeyboard($message);

        $this->assertSame('#2 · Wed, 1 Jan 2020 14:00', $keyboard[0][0]['text']);
        $this->assertSame('#1 · Wed, 1 Jan 2020 10:00', $keyboard[1][0]['text']);
    }

    // --- paginationRow ---

    public function testNoPaginationRowOnSinglePage(): void
    {
        $games = [['game_id' => 1, 'kickoff_at' => '2099-12-31 18:00:00']];
        $pagination = new KeyboardPagination(totalItems: 1, perPage: 5, page: 1);

        $message = $this->gamesListViewBuilder->buildGamesList($games, $pagination);
        $keyboard = $this->extractKeyboard($message);

        // row 0: game button, row 1: back button — no pagination row
        $this->assertCount(2, $keyboard);
        $this->assertSame('#1 · Thu, 31 Dec 2099 18:00', $keyboard[0][0]['text']);
        $this->assertSame('↩ Back', $keyboard[1][0]['text']);
    }

    public function testPaginationRowAppearsOnMultiplePages(): void
    {
        $games = [['game_id' => 1, 'kickoff_at' => '2099-12-31 18:00:00']];
        $pagination = new KeyboardPagination(totalItems: 10, perPage: 5, page: 1);

        $message = $this->gamesListViewBuilder->buildGamesList($games, $pagination);
        $keyboard = $this->extractKeyboard($message);

        // row 0: game button, row 1: pagination, row 2: back button
        $this->assertCount(3, $keyboard);
        $this->assertSame('Next »', $keyboard[1][0]['text']);
        $this->assertSame('↩ Back', $keyboard[2][0]['text']);
    }

    public function testPaginationRowHasBothButtonsOnMiddlePage(): void
    {
        $games = [['game_id' => 1, 'kickoff_at' => '2099-12-31 18:00:00']];
        $pagination = new KeyboardPagination(totalItems: 15, perPage: 5, page: 2);

        $message = $this->gamesListViewBuilder->buildGamesList($games, $pagination);
        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(3, $keyboard);
        $this->assertSame('« Prev', $keyboard[1][0]['text']);
        $this->assertSame('Next »', $keyboard[1][1]['text']);
    }

    // --- helpers ---

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        @mkdir(BASE_LOG_DIR, 0777, true);
        $this->gamesListViewBuilder = new GamesListMessageBuilder();
        $this->gameRepository = new GameRepository($this->db);
    }

    protected function tearDown(): void
    {
        Connection::close();
    }
}
