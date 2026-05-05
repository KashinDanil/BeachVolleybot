<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\ShareGameMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;

final class ShareGameMessageBuilderTest extends TestCase
{
    public function testKeyboardHasSingleShareButton(): void
    {
        $message = $this->build(gameId: 42);

        $keyboard = $this->extractKeyboard($message);

        $this->assertCount(1, $keyboard);
        $this->assertCount(1, $keyboard[0]);
    }

    public function testButtonUsesSwitchInlineQueryWithForwardPrefixAndGameId(): void
    {
        $message = $this->build(gameId: 42);

        $button = $this->extractKeyboard($message)[0][0];

        $this->assertSame('Forward game 42', $button['switch_inline_query']);
        $this->assertArrayNotHasKey('callback_data', $button);
    }

    public function testButtonTextDefaultsToEnglishShare(): void
    {
        $message = $this->build(gameId: 1, language: Language::EN);

        $button = $this->extractKeyboard($message)[0][0];

        $this->assertSame('Share', $button['text']);
    }

    public function testButtonTextIsLocalizedToRussian(): void
    {
        $message = $this->build(gameId: 1, language: Language::RU);

        $button = $this->extractKeyboard($message)[0][0];

        $this->assertSame('Поделиться', $button['text']);
    }

    public function testButtonTextIsLocalizedToSpanish(): void
    {
        $message = $this->build(gameId: 1, language: Language::ES);

        $button = $this->extractKeyboard($message)[0][0];

        $this->assertSame('Compartir', $button['text']);
    }

    public function testBodyTextDefaultsToEnglish(): void
    {
        $message = $this->build(gameId: 1, language: Language::EN);

        $this->assertStringContainsString('Share this game to another chat', $message->getText()->getMessageText());
    }

    public function testBodyTextIsLocalized(): void
    {
        $message = $this->build(gameId: 1, language: Language::RU);

        $this->assertStringContainsString('Поделитесь этой игрой в другом чате', $message->getText()->getMessageText());
    }

    public function testSwitchInlineQueryPayloadIsTheSameAcrossLanguages(): void
    {
        $payloadEn = $this->extractKeyboard($this->build(gameId: 7, language: Language::EN))[0][0]['switch_inline_query'];
        $payloadRu = $this->extractKeyboard($this->build(gameId: 7, language: Language::RU))[0][0]['switch_inline_query'];
        $payloadEs = $this->extractKeyboard($this->build(gameId: 7, language: Language::ES))[0][0]['switch_inline_query'];

        $this->assertSame('Forward game 7', $payloadEn);
        $this->assertSame('Forward game 7', $payloadRu);
        $this->assertSame('Forward game 7', $payloadEs);
    }

    private function build(int $gameId, string $language = Language::EN): TelegramMessage
    {
        $translator = new Translator($language, tempnam(sys_get_temp_dir(), 'bvb_missing_'));

        return new ShareGameMessageBuilder($translator)->build($gameId);
    }

    private function extractKeyboard(TelegramMessage $message): array
    {
        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }
}
