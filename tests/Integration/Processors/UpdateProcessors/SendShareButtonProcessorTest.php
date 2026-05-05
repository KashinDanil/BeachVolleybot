<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\SendShareButtonProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class SendShareButtonProcessorTest extends ProcessorTestCase
{
    public function testSendsShareReplyWhenGameExistsForInlineQueryId(): void
    {
        $gameId = $this->createGame(title: 'Saturday 10:00', createdBy: 200, inlineQueryId: 'query_42');
        $update = $this->buildUpdate(inlineQueryId: 'query_42', fromId: 200, messageId: 139);

        new SendShareButtonProcessor($this->telegramSender)->process($update);

        $sendCall = $this->lastSendMessageCall();
        $this->assertNotNull($sendCall, 'Expected sendMessage to be called');
        $this->assertSame(200, $sendCall['args'][0]);
        $this->assertSame(139, $sendCall['args'][4]);

        $keyboard = json_decode($sendCall['args'][5]->toJson(), true)['inline_keyboard'];
        $this->assertSame("Forward game $gameId", $keyboard[0][0]['switch_inline_query']);
        $this->assertSame('Share', $keyboard[0][0]['text']);
    }

    public function testDoesNothingWhenGameNotFoundForInlineQueryId(): void
    {
        $update = $this->buildUpdate(inlineQueryId: 'unknown_query', fromId: 200);

        new SendShareButtonProcessor($this->telegramSender)->process($update);

        $this->assertMessageNotSent();
    }

    public function testDoesNothingWhenMetaButtonIsAbsent(): void
    {
        $payload = $this->privateViaBotGameMessagePayload(inlineQueryId: 'query_42');
        $payload['message']['reply_markup']['inline_keyboard'] = [];
        $update = TelegramUpdate::fromArray($payload);

        new SendShareButtonProcessor($this->telegramSender)->process($update);

        $this->assertMessageNotSent();
    }

    private function buildUpdate(
        string $inlineQueryId,
        int $fromId = 200,
        string $firstName = 'Danil',
        int $messageId = 139,
    ): TelegramUpdate {
        return TelegramUpdate::fromArray(
            $this->privateViaBotGameMessagePayload($inlineQueryId, $fromId, $firstName, $messageId),
        );
    }

    private function lastSendMessageCall(): ?array
    {
        $calls = array_filter($this->bot->calls, fn($call) => 'sendMessage' === $call['method']);

        if (empty($calls)) {
            return null;
        }

        return end($calls);
    }
}
