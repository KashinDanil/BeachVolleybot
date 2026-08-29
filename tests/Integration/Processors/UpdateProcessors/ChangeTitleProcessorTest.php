<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Processors\UpdateProcessors\ChangeTitleProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

/**
 * Who may rename a game, and what counts as a title, is ChangeTitleHandler's business — see
 * ChangeTitleHandlerTest. What is left here is applying the rename, plus the guards the
 * processor keeps for itself because they outlive routing: a game whose day has passed and
 * a reply to a message that carries no game.
 */
final class ChangeTitleProcessorTest extends ProcessorTestCase
{
    private const int CREATOR_ID = 200;

    public function testCreatorRenamesGame(): void
    {
        $gameId = $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic 31.12.2099 20:00'));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Picnic 31.12.2099 20:00', $title);
    }

    public function testCreatorUserTimeIsUpdated(): void
    {
        $gameId = $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic 31.12.2099 20:00'));

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, self::CREATOR_ID);
        $this->assertSame('20:00', $gameUser['time']);
    }

    public function testRefreshesInlineMessageOnSuccess(): void
    {
        $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic 31.12.2099 20:00'));

        $this->assertMessageEdited();
    }

    public function testDeletesUserReplyOnSuccess(): void
    {
        $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic 31.12.2099 20:00'));

        $this->assertMessageDeleted();
    }

    public function testPastDayGameRejectsRename(): void
    {
        $gameId = $this->createGame(
            title: 'Old Game 01.01.2020 18:00',
            createdBy: self::CREATOR_ID,
            inlineMessageId: 'msg_1',
            gameKey: 'query_1',
        );

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic 31.12.2099 20:00'));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Old Game 01.01.2020 18:00', $title);
        $this->assertMessageNotEdited();
    }

    public function testReplyToBotMessageWithoutGameCallbackIsIgnored(): void
    {
        $gameId = $this->seedGameOwnedByCreator();

        $payload = $this->replyMessagePayload('Picnic 31.12.2099 20:00', 'query_1', fromId: self::CREATOR_ID);
        unset($payload['message']['reply_to_message']['reply_markup']);

        new ChangeTitleProcessor($this->telegramSender)
            ->process(TelegramUpdate::fromArray($payload));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Bogatell 31.12.2099 18:00', $title);
        $this->assertMessageNotEdited();
    }

    private function seedGameOwnedByCreator(): int
    {
        $gameId = $this->createGame(
            title: 'Bogatell 31.12.2099 18:00',
            createdBy: self::CREATOR_ID,
            inlineMessageId: 'msg_1',
            gameKey: 'query_1',
        );
        $this->createUser(self::CREATOR_ID);
        $this->db->insert('game_users', [
            'game_id' => $gameId,
            'telegram_user_id' => self::CREATOR_ID,
            'time' => '18:00',
            'volleyball' => 1,
            'net' => 1,
        ]);
        $this->createSlot($gameId, self::CREATOR_ID, 1);

        return $gameId;
    }

    private function buildUpdate(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->replyMessagePayload($text, 'query_1', fromId: self::CREATOR_ID),
        );
    }
}
