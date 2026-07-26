<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Processors\UpdateProcessors\ChangeTitleProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class ChangeTitleProcessorTest extends ProcessorTestCase
{
    private const int CREATOR_ID = 200;
    private const int NON_CREATOR_ID = 201;

    public function testCreatorRenamesGame(): void
    {
        $gameId = $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic Sunday 20:00', self::CREATOR_ID));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Picnic Sunday 20:00', $title);
    }

    public function testCreatorUserTimeIsUpdated(): void
    {
        $gameId = $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic Sunday 20:00', self::CREATOR_ID));

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, self::CREATOR_ID);
        $this->assertSame('20:00', $gameUser['time']);
    }

    public function testRefreshesInlineMessageOnSuccess(): void
    {
        $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic Sunday 20:00', self::CREATOR_ID));

        $this->assertMessageEdited();
    }

    public function testDeletesUserReplyOnSuccess(): void
    {
        $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic Sunday 20:00', self::CREATOR_ID));

        $deleteCalls = array_filter($this->bot->calls, fn($c) => 'deleteMessage' === $c['method']);
        $this->assertNotEmpty($deleteCalls);
    }

    public function testNonCreatorCannotRename(): void
    {
        $gameId = $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic Sunday 20:00', self::NON_CREATOR_ID));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Friday Game 18:00', $title);
        $this->assertMessageNotEdited();
    }

    public function testAdminCanRenameGameOwnedByAnotherUserInPrivateChat(): void
    {
        $gameId = $this->seedGameOwnedByCreator();
        $this->seedAdmin();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildPrivateChatUpdate('Picnic Sunday 18:00', self::ADMIN_TELEGRAM_USER_ID));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Picnic Sunday 18:00', $title);
    }

    public function testAdminCannotRenameGameOwnedByAnotherUserInGroupChat(): void
    {
        $gameId = $this->seedGameOwnedByCreator();
        $this->seedAdmin();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Picnic Sunday 20:00', self::ADMIN_TELEGRAM_USER_ID));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Friday Game 18:00', $title);
        $this->assertMessageNotEdited();
    }

    public function testInvalidTitleIsRejected(): void
    {
        $gameId = $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('no time here', self::CREATOR_ID));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Friday Game 18:00', $title);
        $this->assertMessageNotEdited();
    }

    public function testPastDayGameRejectsRename(): void
    {
        $gameId = $this->createGame(
            title: 'Old Game 01.01.20 18:00',
            createdBy: self::CREATOR_ID,
            inlineMessageId: 'msg_1',
            gameKey: 'query_1',
        );

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Beach Sunday 20:00', self::CREATOR_ID));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Old Game 01.01.20 18:00', $title);
        $this->assertMessageNotEdited();
    }

    public function testCannotRenameToPastDate(): void
    {
        $gameId = $this->seedGameOwnedByCreator();

        new ChangeTitleProcessor($this->telegramSender)
            ->process($this->buildUpdate('Old Picnic 01.01.20 20:00', self::CREATOR_ID));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Friday Game 18:00', $title);
        $this->assertMessageNotEdited();
    }

    public function testReplyToBotMessageWithoutGameCallbackIsIgnored(): void
    {
        $gameId = $this->seedGameOwnedByCreator();

        $payload = $this->replyMessagePayload('Picnic Sunday 20:00', 'query_1', fromId: self::CREATOR_ID);
        unset($payload['message']['reply_to_message']['reply_markup']);

        new ChangeTitleProcessor($this->telegramSender)
            ->process(TelegramUpdate::fromArray($payload));

        $title = new GameRepository($this->db)->findTitleByGameId($gameId);
        $this->assertSame('Friday Game 18:00', $title);
        $this->assertMessageNotEdited();
    }

    private function seedGameOwnedByCreator(): int
    {
        $gameId = $this->createGame(
            title: 'Friday Game 18:00',
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

    private function buildUpdate(string $text, int $fromId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->replyMessagePayload($text, 'query_1', fromId: $fromId),
        );
    }

    private function buildPrivateChatUpdate(string $text, int $fromId): TelegramUpdate
    {
        $payload = $this->replyMessagePayload($text, 'query_1', fromId: $fromId);
        $payload['message']['chat'] = ['id' => $fromId, 'type' => 'private'];
        $payload['message']['reply_to_message']['chat'] = ['id' => $fromId, 'type' => 'private'];

        return TelegramUpdate::fromArray($payload);
    }
}
