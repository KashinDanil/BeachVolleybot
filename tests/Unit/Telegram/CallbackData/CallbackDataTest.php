<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\CallbackData;

use BeachVolleybot\Processors\UpdateProcessors\GameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
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
        $json = GameCallbackData::create(GameCallbackAction::Join)->toJson();

        $this->assertSame('{"a":"j"}', $json);
    }

    public function testToJsonWithInlineQueryId(): void
    {
        $json = GameCallbackData::create(GameCallbackAction::Leave)->withGameKey('q_42')->toJson();

        $this->assertSame('{"a":"l","q":"q_42"}', $json);
    }

    public function testToJsonOmitsNullInlineQueryId(): void
    {
        $decoded = json_decode(GameCallbackData::create(GameCallbackAction::Join)->toJson(), true);

        $this->assertArrayNotHasKey('q', $decoded);
    }

    // --- fromJson ---

    public function testFromJsonRestoresAction(): void
    {
        $json = GameCallbackData::create(GameCallbackAction::AddVolleyball)->toJson();

        $this->assertSame(GameCallbackAction::AddVolleyball, GameCallbackData::fromJson($json)?->getAction());
    }

    public function testFromJsonReturnsNullForNullInput(): void
    {
        $this->assertNull(GameCallbackData::fromJson(null));
    }

    public function testFromJsonReturnsNullForUnknownAction(): void
    {
        $this->assertNull(GameCallbackData::fromJson('{"a":"unknown"}'));
    }

    public function testFromJsonReturnsNullForMalformedJson(): void
    {
        // A foreign bot's plain-string callback_data must yield null, not throw.
        $this->assertNull(GameCallbackData::fromJson('vote_yes'));
    }

    public function testFromJsonReturnsNullForNonArrayJson(): void
    {
        $this->assertNull(GameCallbackData::fromJson('42'));
    }

    public function testFromJsonRestoresInlineQueryId(): void
    {
        $json = GameCallbackData::create(GameCallbackAction::Leave)->withGameKey('q_99')->toJson();

        $this->assertSame('q_99', GameCallbackData::fromJson($json)?->getGameKey());
    }

    // --- roundtrip ---

    public function testRoundtripForAllActions(): void
    {
        foreach (GameCallbackAction::cases() as $action) {
            $json = GameCallbackData::create($action)->toJson();

            $this->assertSame($action, GameCallbackData::fromJson($json)?->getAction(), "Roundtrip failed for {$action->name}");
        }
    }

    // --- extractGameKey ---

    public function testExtractInlineQueryIdFromMetaButton(): void
    {
        $message = $this->messageWithMetaButton(
            GameCallbackData::create(GameCallbackAction::Leave)->withGameKey('q_123')->toJson(),
        );

        $this->assertSame('q_123', GameCallbackData::extractGameKey($message));
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

        $this->assertNull(GameCallbackData::extractGameKey($message));
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

        $this->assertNull(GameCallbackData::extractGameKey($message));
    }

    public function testExtractInlineQueryIdReturnsNullWhenKeyMissing(): void
    {
        $message = $this->messageWithMetaButton('{"a":"j"}');

        $this->assertNull(GameCallbackData::extractGameKey($message));
    }

    public function testExtractGameKeyReturnsNullForForeignPlainStringCallbackData(): void
    {
        // Replying to another bot's keyboard (plain-string callback_data) must not throw.
        $message = $this->messageWithMetaButton('vote_yes');

        $this->assertNull(GameCallbackData::extractGameKey($message));
    }
}
