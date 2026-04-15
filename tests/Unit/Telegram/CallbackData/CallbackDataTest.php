<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\CallbackData;

use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramChat;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineKeyboardButton;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineKeyboardMarkup;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use PHPUnit\Framework\TestCase;

final class CallbackDataTest extends TestCase
{
    // --- encode ---

    public function testEncodeActionOnly(): void
    {
        $json = CallbackData::encode(CallbackAction::Join);

        $this->assertSame('{"a":"j"}', $json);
    }

    public function testEncodeActionWithInlineQueryId(): void
    {
        $json = CallbackData::encode(CallbackAction::Leave, 'q_42');

        $this->assertSame('{"a":"l","q":"q_42"}', $json);
    }

    public function testEncodeNullInlineQueryIdOmitsKey(): void
    {
        $decoded = json_decode(CallbackData::encode(CallbackAction::Join), true);

        $this->assertArrayNotHasKey('q', $decoded);
    }

    // --- extractAction ---

    public function testExtractActionFromEncodedData(): void
    {
        $json = CallbackData::encode(CallbackAction::AddVolleyball);

        $this->assertSame(CallbackAction::AddVolleyball, CallbackData::extractAction($json));
    }

    public function testExtractActionReturnsNullForNullInput(): void
    {
        $this->assertNull(CallbackData::extractAction(null));
    }

    public function testExtractActionReturnsNullForUnknownAction(): void
    {
        $this->assertNull(CallbackData::extractAction('{"a":"unknown"}'));
    }

    // --- encode + extractAction roundtrip ---

    public function testRoundtripForAllActions(): void
    {
        foreach (CallbackAction::cases() as $action) {
            $json = CallbackData::encode($action);

            $this->assertSame($action, CallbackData::extractAction($json), "Roundtrip failed for {$action->name}");
        }
    }

    // --- extractInlineQueryId ---

    public function testExtractInlineQueryIdFromMetaButton(): void
    {
        $message = $this->messageWithMetaButton(
            CallbackData::encode(CallbackAction::Leave, 'q_123'),
        );

        $this->assertSame('q_123', CallbackData::extractInlineQueryId($message));
    }

    private function messageWithMetaButton(?string $callbackData): TelegramMessage
    {
        return new TelegramMessage(
            messageId: 1,
            from: new TelegramUser(id: 1, firstName: 'Test'),
            chat: new TelegramChat(id: 1, type: 'private'),
            date: time(),
            replyMarkup: new TelegramInlineKeyboardMarkup([
                [
                    new TelegramInlineKeyboardButton(text: 'Leave', callbackData: $callbackData),
                ],
            ]),
        );
    }

    public function testExtractInlineQueryIdReturnsNullWhenNoReplyMarkup(): void
    {
        $message = $this->messageWithoutMarkup();

        $this->assertNull(CallbackData::extractInlineQueryId($message));
    }

    private function messageWithoutMarkup(): TelegramMessage
    {
        return new TelegramMessage(
            messageId: 1,
            from: new TelegramUser(id: 1, firstName: 'Test'),
            chat: new TelegramChat(id: 1, type: 'private'),
            date: time(),
        );
    }

    // --- Helpers ---

    public function testExtractInlineQueryIdReturnsNullWhenNoCallbackData(): void
    {
        $message = $this->messageWithMetaButton(null);

        $this->assertNull(CallbackData::extractInlineQueryId($message));
    }

    public function testExtractInlineQueryIdReturnsNullWhenKeyMissing(): void
    {
        $message = $this->messageWithMetaButton('{"a":"j"}');

        $this->assertNull(CallbackData::extractInlineQueryId($message));
    }
}