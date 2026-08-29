<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\GameHandlers;

use BeachVolleybot\Processors\Handlers\GameHandlers\ChangeTitleHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

/**
 * The handler is the rename gate: a reply it turns down is not rejected, it is routed to
 * JoinWithTimeHandler instead. So every rule about who may rename a game, and what makes a
 * title, is asserted here rather than on the processor.
 */
final class ChangeTitleHandlerTest extends ProcessorTestCase
{
    private const int CREATOR_ID = 200;
    private const int NON_CREATOR_ID = 201;

    public function testMatchesRenameByCreator(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertTrue($this->handlerMatches('Bogatell 31.12.2099 20:00', self::CREATOR_ID));
    }

    public function testDoesNotMatchRenameByNonCreator(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertFalse($this->handlerMatches('Bogatell 31.12.2099 20:00', self::NON_CREATOR_ID));
    }

    public function testDoesNotMatchRenameByAdminInGroupChat(): void
    {
        $this->seedGameOwnedByCreator();
        $this->seedAdmin();

        $this->assertFalse($this->handlerMatches('Bogatell 31.12.2099 20:00', self::ADMIN_TELEGRAM_USER_ID));
    }

    public function testMatchesRenameByAdminInPrivateChat(): void
    {
        $this->seedGameOwnedByCreator();
        $this->seedAdmin();

        $this->assertTrue($this->handlerMatchesInPrivateChat('Bogatell 31.12.2099 20:00', self::ADMIN_TELEGRAM_USER_ID));
    }

    public function testDoesNotMatchRenameByOtherUserInPrivateChat(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertFalse($this->handlerMatchesInPrivateChat('Bogatell 31.12.2099 20:00', self::NON_CREATOR_ID));
    }

    public function testDoesNotMatchTitleWithoutDate(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertFalse($this->handlerMatches('Bogatell 20:00', self::CREATOR_ID));
    }

    public function testDoesNotMatchTitleWithoutTime(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertFalse($this->handlerMatches('Bogatell 31.12.2099', self::CREATOR_ID));
    }

    public function testDoesNotMatchTitleWithoutDateOrTime(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertFalse($this->handlerMatches('Bogatell', self::CREATOR_ID));
    }

    public function testDoesNotMatchTitleWithPastKickoffDay(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertFalse($this->handlerMatches('Bogatell 01.01.2020 20:00', self::CREATOR_ID));
    }

    // A day of week is the one title shape whose date depends on the game, so these two use
    // it deliberately where the other fixtures stick to absolute dates.
    public function testMatchesDayOfWeekTitleStillAheadOfTheGamesCreationDate(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertTrue($this->handlerMatches('Bogatell Friday 20:00', self::CREATOR_ID));
    }

    public function testDoesNotMatchDayOfWeekTitleAlreadyPastForTheGamesCreationDate(): void
    {
        $gameId = $this->seedGameOwnedByCreator();
        $this->setGameCreatedAt($gameId, '2020-01-01 10:00:00');

        // Anchored on the game, 'Friday' is 03.01.2020 — resolving it against today instead
        // would make this rename look valid, and every other reader would still see the past.
        $this->assertFalse($this->handlerMatches('Bogatell Friday 20:00', self::CREATOR_ID));
    }

    public function testDoesNotMatchBareTimeReply(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertFalse($this->handlerMatches('20:00', self::CREATOR_ID));
    }

    public function testDoesNotMatchChatterWithTimeInIt(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertFalse($this->handlerMatches('I can only make it by 20:30 folks', self::CREATOR_ID));
    }

    public function testDoesNotMatchReplyToUnknownGame(): void
    {
        $this->seedGameOwnedByCreator();

        $update = TelegramUpdate::fromArray(
            $this->replyMessagePayload('Bogatell 31.12.2099 20:00', 'unknown_key', fromId: self::CREATOR_ID),
        );

        $this->assertFalse(new ChangeTitleHandler()->matches($update));
    }

    public function testDoesNotMatchReplyToBotMessageWithoutGameCallback(): void
    {
        $this->seedGameOwnedByCreator();

        $payload = $this->replyMessagePayload('Bogatell 31.12.2099 20:00', 'query_1', fromId: self::CREATOR_ID);
        unset($payload['message']['reply_to_message']['reply_markup']);

        $this->assertFalse(new ChangeTitleHandler()->matches(TelegramUpdate::fromArray($payload)));
    }

    public function testChatterCostsNoQuery(): void
    {
        $this->seedGameOwnedByCreator();

        $queries = $this->queriesWhileMatching('sounds good to me', self::CREATOR_ID);

        $this->assertSame([], $queries);
    }

    public function testBareTimeCostsNoQuery(): void
    {
        $this->seedGameOwnedByCreator();

        $queries = $this->queriesWhileMatching('20:00', self::CREATOR_ID);

        $this->assertSame([], $queries);
    }

    public function testTitleWithoutDateCostsNoQuery(): void
    {
        $this->seedGameOwnedByCreator();

        $queries = $this->queriesWhileMatching('Bogatell 20:00', self::CREATOR_ID);

        $this->assertSame([], $queries);
    }

    public function testTitleWithPastKickoffDayReadsTheGameOnly(): void
    {
        $this->seedGameOwnedByCreator();

        // The kickoff day is judged against the game's creation date, so this one does cost
        // the game read — but never the role read that follows it.
        $queries = $this->queriesWhileMatching('Bogatell 01.01.2020 20:00', self::CREATOR_ID);

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('games', $queries[0]);
    }

    public function testTitleShapedReplyReadsTheGameOnly(): void
    {
        $this->seedGameOwnedByCreator();

        $queries = $this->queriesWhileMatching('Bogatell 31.12.2099 20:00', self::CREATOR_ID);

        // The author's role is a DM question, so a group reply never asks it.
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('games', $queries[0]);
    }

    public function testTitleShapedReplyInPrivateChatReadsTheGameAndTheRole(): void
    {
        $this->seedGameOwnedByCreator();

        $queries = $this->queriesWhileMatchingInPrivateChat('Bogatell 31.12.2099 20:00', self::NON_CREATOR_ID);

        $this->assertCount(2, $queries);
        $this->assertStringContainsString('games', $queries[0]);
        $this->assertStringContainsString('users', $queries[1]);
    }

    public function testChatterInPrivateChatCostsNoQuery(): void
    {
        $this->seedGameOwnedByCreator();

        $queries = $this->queriesWhileMatchingInPrivateChat('sounds good to me', self::NON_CREATOR_ID);

        $this->assertSame([], $queries);
    }

    private function seedGameOwnedByCreator(): int
    {
        return $this->seedFullGame(gameKey: 'query_1', createdBy: self::CREATOR_ID);
    }

    private function setGameCreatedAt(int $gameId, string $createdAt): void
    {
        $this->db->update('games', ['created_at' => $createdAt], ['game_id' => $gameId]);
    }

    /**
     * @return string[]
     */
    private function queriesWhileMatching(string $text, int $fromId): array
    {
        return $this->queriesDuring(fn() => $this->handlerMatches($text, $fromId));
    }

    /**
     * @return string[]
     */
    private function queriesWhileMatchingInPrivateChat(string $text, int $fromId): array
    {
        return $this->queriesDuring(fn() => $this->handlerMatchesInPrivateChat($text, $fromId));
    }

    private function handlerMatches(string $text, int $fromId): bool
    {
        return new ChangeTitleHandler()->matches(
            TelegramUpdate::fromArray($this->replyMessagePayload($text, 'query_1', fromId: $fromId)),
        );
    }

    private function handlerMatchesInPrivateChat(string $text, int $fromId): bool
    {
        return new ChangeTitleHandler()->matches(
            TelegramUpdate::fromArray($this->privateReplyMessagePayload($text, 'query_1', fromId: $fromId)),
        );
    }
}
