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
    // --- toJson ---

    public function testToJsonActionOnly(): void
    {
        $json = CallbackData::create(CallbackAction::Join)->toJson();

        $this->assertSame('{"a":"j"}', $json);
    }

    public function testToJsonWithInlineQueryId(): void
    {
        $json = CallbackData::create(CallbackAction::Leave)->withInlineQueryId('q_42')->toJson();

        $this->assertSame('{"a":"l","q":"q_42"}', $json);
    }

    public function testToJsonOmitsNullInlineQueryId(): void
    {
        $decoded = json_decode(CallbackData::create(CallbackAction::Join)->toJson(), true);

        $this->assertArrayNotHasKey('q', $decoded);
    }

    // --- fromJson ---

    public function testFromJsonRestoresAction(): void
    {
        $json = CallbackData::create(CallbackAction::AddVolleyball)->toJson();

        $this->assertSame(CallbackAction::AddVolleyball, CallbackData::fromJson($json)?->getAction());
    }

    public function testFromJsonReturnsNullForNullInput(): void
    {
        $this->assertNull(CallbackData::fromJson(null));
    }

    public function testFromJsonReturnsNullForUnknownAction(): void
    {
        $this->assertNull(CallbackData::fromJson('{"a":"unknown"}'));
    }

    public function testFromJsonRestoresInlineQueryId(): void
    {
        $json = CallbackData::create(CallbackAction::Leave)->withInlineQueryId('q_99')->toJson();

        $this->assertSame('q_99', CallbackData::fromJson($json)?->getInlineQueryId());
    }

    // --- roundtrip ---

    public function testRoundtripForAllActions(): void
    {
        foreach (CallbackAction::cases() as $action) {
            $json = CallbackData::create($action)->toJson();

            $this->assertSame($action, CallbackData::fromJson($json)?->getAction(), "Roundtrip failed for {$action->name}");
        }
    }

    // --- extractInlineQueryId ---

    public function testExtractInlineQueryIdFromMetaButton(): void
    {
        $message = $this->messageWithMetaButton(
            CallbackData::create(CallbackAction::Leave)->withInlineQueryId('q_123')->toJson(),
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
