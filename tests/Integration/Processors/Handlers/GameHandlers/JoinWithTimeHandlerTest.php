<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\GameHandlers;

use BeachVolleybot\Processors\Handlers\GameHandlers\JoinWithTimeHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

/**
 * The join takes every text reply that mentions a time and that ChangeTitleHandler has not
 * claimed — which is what makes a time buried in ordinary chatter, or a title from someone
 * who may not rename the game, land on a slot instead of being dropped.
 */
final class JoinWithTimeHandlerTest extends ProcessorTestCase
{
    private const int CREATOR_ID = 200;
    private const int NON_CREATOR_ID = 201;

    public function testMatchesBareTimeReply(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertTrue($this->handlerMatches('20:00', self::NON_CREATOR_ID));
    }

    public function testMatchesBareTimeReplyFromTheCreator(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertTrue($this->handlerMatches('20:00', self::CREATOR_ID));
    }

    public function testMatchesTimeInsideOtherText(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertTrue($this->handlerMatches('I can only make it by 20:30 folks', self::NON_CREATOR_ID));
    }

    public function testMatchesTimeInsideOtherTextFromTheCreator(): void
    {
        $this->seedGameOwnedByCreator();

        // Chatter is not a title, so even the creator lands on the join.
        $this->assertTrue($this->handlerMatches('I can only make it by 20:30 folks', self::CREATOR_ID));
    }

    public function testMatchesWellFormedTitleFromSomeoneWhoCannotRename(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertTrue($this->handlerMatches('Bogatell 31.12.2099 20:00', self::NON_CREATOR_ID));
    }

    public function testMatchesWellFormedTitleFromTheCreator(): void
    {
        $this->seedGameOwnedByCreator();

        // The handler knows nothing about renames — it is a reply with a time like any
        // other. ChangeTitleHandler is listed first, so the rename wins; see
        // HandlerExclusivityTest and ProcessorRegistryTest.
        $this->assertTrue($this->handlerMatches('Bogatell 31.12.2099 20:00', self::CREATOR_ID));
    }

    public function testDoesNotMatchTextWithoutTime(): void
    {
        $this->seedGameOwnedByCreator();

        $this->assertFalse($this->handlerMatches('sounds good to me', self::NON_CREATOR_ID));
    }

    public function testDoesNotMatchReplyToBotMessageWithoutGameCallback(): void
    {
        $this->seedGameOwnedByCreator();

        $payload = $this->replyMessagePayload('20:00', 'query_1', fromId: self::NON_CREATOR_ID);
        unset($payload['message']['reply_to_message']['reply_markup']);

        $this->assertFalse(new JoinWithTimeHandler()->matches(TelegramUpdate::fromArray($payload)));
    }

    public function testMatchingCostsNoQuery(): void
    {
        $this->seedGameOwnedByCreator();

        $queries = $this->queriesDuring(function (): void {
            $this->handlerMatches('20:00', self::NON_CREATOR_ID);
            $this->handlerMatches('I can only make it by 20:30 folks', self::NON_CREATOR_ID);
            $this->handlerMatches('Bogatell 31.12.2099 20:00', self::CREATOR_ID);
            $this->handlerMatches('sounds good to me', self::NON_CREATOR_ID);
        });

        $this->assertSame([], $queries);
    }

    private function seedGameOwnedByCreator(): int
    {
        return $this->seedFullGame(gameKey: 'query_1', createdBy: self::CREATOR_ID);
    }

    private function handlerMatches(string $text, int $fromId): bool
    {
        return new JoinWithTimeHandler()->matches(
            TelegramUpdate::fromArray($this->replyMessagePayload($text, 'query_1', fromId: $fromId)),
        );
    }
}
