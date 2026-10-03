<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameMessageManager;
use BeachVolleybot\Processors\UpdateProcessors\ForwardGameProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\MessageAddress;
use BeachVolleybot\Tests\Fixtures\CreatesGameMessageRecords;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class ForwardGameProcessorTest extends ProcessorTestCase
{
    use CreatesGameMessageRecords;

    public function testAttachesNewInlineMessageIdWhenCallerIsCreator(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 200, inlineMessageId: 'msg_original');
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: "Forward game $gameId");

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedTargets = new GameMessageManager()->findGameMessageRecordsByGameId($gameId);
        $this->assertEqualsCanonicalizing(
            [MessageAddress::inline('msg_original'), MessageAddress::inline('msg_forwarded')],
            $this->messageAddresses($attachedTargets),
        );
        $this->assertEqualsCanonicalizing([null, 'query_1'], array_column($attachedTargets, 'inlineQueryId'));
    }

    public function testDoesNothingWhenGameDoesNotExist(): void
    {
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: 'Forward game 9999');

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedId = new GameMessageManager()->resolveGameIdByInlineMessageId('msg_forwarded');
        $this->assertNull($attachedId);
    }

    public function testDoesNothingWhenCallerIsNotCreator(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 100, inlineMessageId: 'msg_original');
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: "Forward game $gameId", fromId: 200);

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedTargets = new GameMessageManager()->findGameMessageRecordsByGameId($gameId);
        $this->assertEquals([MessageAddress::inline('msg_original')], $this->messageAddresses($attachedTargets));
    }

    public function testAttachesNewInlineMessageIdWhenCallerIsAdminButNotCreator(): void
    {
        $this->seedAdmin();
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 100, inlineMessageId: 'msg_original');
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: "Forward game $gameId", fromId: self::ADMIN_TELEGRAM_USER_ID);

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedTargets = new GameMessageManager()->findGameMessageRecordsByGameId($gameId);
        $this->assertEqualsCanonicalizing(
            [MessageAddress::inline('msg_original'), MessageAddress::inline('msg_forwarded')],
            $this->messageAddresses($attachedTargets),
        );
        $this->assertEqualsCanonicalizing([null, 'query_1'], array_column($attachedTargets, 'inlineQueryId'));
    }

    public function testDoesNothingWhenQueryIsNotForwardPattern(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 200, inlineMessageId: 'msg_original');
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: 'Saturday 18:00');

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedTargets = new GameMessageManager()->findGameMessageRecordsByGameId($gameId);
        $this->assertEquals([MessageAddress::inline('msg_original')], $this->messageAddresses($attachedTargets));
    }

    private function buildUpdate(
        string $inlineMessageId,
        string $query,
        string $resultId = 'query_1',
        int $fromId = 200,
        string $firstName = 'Danil',
    ): TelegramUpdate {
        return TelegramUpdate::fromArray(
            $this->chosenInlineResultPayload($inlineMessageId, $resultId, $query, $fromId, $firstName),
        );
    }
}
