<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Processors\UpdateProcessors\ForwardGameProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\Targets\InlineGameMessageTarget;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class ForwardGameProcessorTest extends ProcessorTestCase
{
    public function testAttachesNewInlineMessageIdWhenCallerIsCreator(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 200, inlineMessageId: 'msg_original');
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: "Forward game $gameId");

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedTargets = new GameMessageRepository($this->db)->findTargetsByGameId($gameId);
        $this->assertEqualsCanonicalizing(
            [new InlineGameMessageTarget('msg_original'), new InlineGameMessageTarget('msg_forwarded')],
            $attachedTargets,
        );
    }

    public function testDoesNothingWhenGameDoesNotExist(): void
    {
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: 'Forward game 9999');

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedId = new GameMessageRepository($this->db)->findGameIdByInlineMessageId('msg_forwarded');
        $this->assertNull($attachedId);
    }

    public function testDoesNothingWhenCallerIsNotCreator(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 100, inlineMessageId: 'msg_original');
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: "Forward game $gameId", fromId: 200);

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedTargets = new GameMessageRepository($this->db)->findTargetsByGameId($gameId);
        $this->assertEquals([new InlineGameMessageTarget('msg_original')], $attachedTargets);
    }

    public function testAttachesNewInlineMessageIdWhenCallerIsAdminButNotCreator(): void
    {
        $this->seedAdmin();
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 100, inlineMessageId: 'msg_original');
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: "Forward game $gameId", fromId: self::ADMIN_TELEGRAM_USER_ID);

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedTargets = new GameMessageRepository($this->db)->findTargetsByGameId($gameId);
        $this->assertEqualsCanonicalizing(
            [new InlineGameMessageTarget('msg_original'), new InlineGameMessageTarget('msg_forwarded')],
            $attachedTargets,
        );
    }

    public function testDoesNothingWhenQueryIsNotForwardPattern(): void
    {
        $gameId = $this->createGame(title: 'Saturday 18:00', createdBy: 200, inlineMessageId: 'msg_original');
        $update = $this->buildUpdate(inlineMessageId: 'msg_forwarded', query: 'Saturday 18:00');

        new ForwardGameProcessor($this->telegramSender)->process($update);

        $attachedTargets = new GameMessageRepository($this->db)->findTargetsByGameId($gameId);
        $this->assertEquals([new InlineGameMessageTarget('msg_original')], $attachedTargets);
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
