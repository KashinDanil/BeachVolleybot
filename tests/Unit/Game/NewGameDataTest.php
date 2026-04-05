<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\GameBuilder;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use PHPUnit\Framework\TestCase;

final class NewGameDataTest extends TestCase
{
    private TelegramUser $creator;

    protected function setUp(): void
    {
        $this->creator = new TelegramUser(id: 200, firstName: 'Alice', lastName: 'Smith', username: 'alice');
    }

    public function testGameRowContainsTitleAndIds(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Friday Game 18:00', 'query_1', 'msg_1');

        $this->assertSame('Friday Game 18:00', $data->gameRow['title']);
        $this->assertSame('query_1', $data->gameRow['inline_query_id']);
        $this->assertSame('msg_1', $data->gameRow['inline_message_id']);
    }

    public function testInlineMessageIdDefaultsToEmptyString(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Game 18:00', 'query_1');

        $this->assertSame('', $data->gameRow['inline_message_id']);
    }

    public function testSlotRowHasPositionOne(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Game 18:00', 'query_1');

        $this->assertSame(200, $data->slotRow['telegram_user_id']);
        $this->assertSame(1, $data->slotRow['position']);
    }

    public function testGamePlayerRowHasInitialVolleyballAndNet(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Game 18:00', 'query_1');

        $this->assertSame(NewGameData::INITIAL_VOLLEYBALL, $data->gamePlayerRow['volleyball']);
        $this->assertSame(NewGameData::INITIAL_NET, $data->gamePlayerRow['net']);
        $this->assertNull($data->gamePlayerRow['time']);
    }

    public function testPlayerRowContainsCreatorData(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Game 18:00', 'query_1');

        $this->assertSame(200, $data->playerRow['telegram_user_id']);
        $this->assertSame('Alice', $data->playerRow['first_name']);
        $this->assertSame('Smith', $data->playerRow['last_name']);
        $this->assertSame('alice', $data->playerRow['username']);
    }

    public function testGameBuilderBuildsValidGameFromData(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Friday Game 18:00', 'query_1');

        $game = GameBuilder::fromNewGameData($data);

        $this->assertSame('Friday Game 18:00', $game->getTitle());
        $this->assertSame('query_1', $game->getInlineQueryId());
        $this->assertCount(1, $game->getPlayers());
    }

    public function testGameBuilderPlayerHasCorrectEquipment(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Game 18:00', 'query_1');

        $player = GameBuilder::fromNewGameData($data)->getPlayers()[0];

        $this->assertSame('Alice Smith', $player->getName());
        $this->assertSame(NewGameData::INITIAL_VOLLEYBALL, $player->getVolleyball());
        $this->assertSame(NewGameData::INITIAL_NET, $player->getNet());
    }
}
