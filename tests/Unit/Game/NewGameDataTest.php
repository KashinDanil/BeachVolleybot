<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Game\NewGameFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use PHPUnit\Framework\TestCase;

final class NewGameDataTest extends TestCase
{
    private TelegramUser $creator;

    protected function setUp(): void
    {
        $this->creator = new TelegramUser(id: 200, firstName: 'Alice', lastName: 'Smith', username: 'alice');
    }

    public function testFromUserMapsCreatorFields(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Game 18:00', 'query_1');

        $this->assertSame(200, $data->telegramUserId);
        $this->assertSame('Alice', $data->firstName);
        $this->assertSame('Smith', $data->lastName);
        $this->assertSame('alice', $data->username);
    }

    public function testFromUserMapsGameFields(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Friday Game 18:00', 'query_1');

        $this->assertSame('Friday Game 18:00', $data->title);
        $this->assertSame('query_1', $data->inlineQueryId);
    }

    public function testBuildFromNewGameDataProducesValidGame(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Friday Game 18:00', 'query_1');

        $game = NewGameFactory::create($data);

        $this->assertSame('Friday Game 18:00', $game->getTitle());
        $this->assertSame('query_1', $game->getInlineQueryId());
        $this->assertCount(1, $game->getUsers());
    }

    public function testBuildFromNewGameDataSetsCreatorTimeFromTitle(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Friday Game 18:00', 'query_1');

        $user = NewGameFactory::create($data)->getUsers()[0];

        $this->assertSame('18:00', $user->getTime());
    }

    public function testBuildFromNewGameDataSetsInitialEquipment(): void
    {
        $data = NewGameData::fromUser($this->creator, 'Game 18:00', 'query_1');

        $user = NewGameFactory::create($data)->getUsers()[0];

        $this->assertSame('Alice Smith', $user->getName());
        $this->assertSame(NewGameData::INITIAL_VOLLEYBALL, $user->getVolleyball());
        $this->assertSame(NewGameData::INITIAL_NET, $user->getNet());
    }
}
