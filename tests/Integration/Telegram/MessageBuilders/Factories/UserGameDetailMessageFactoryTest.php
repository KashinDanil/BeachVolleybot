<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Factories\UserGameDetailMessageFactory;
use BeachVolleybot\Telegram\MessageBuilders\ShareGameMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;

final class UserGameDetailMessageFactoryTest extends DatabaseTestCase
{
    // Anchored to an explicit far-future date so `isKickoffPast` is stable regardless of when
    // the suite runs. Past-game tests in this file use the symmetric `01.01.2020` past-anchor.
    private const string GAME_TITLE = 'Friday 31.12.2099 18:00';

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testReturnsNullForNonexistentGame(): void
    {
        $this->assertNull(
            UserGameDetailMessageFactory::build(gameId: 99999, listPage: 1, translator: new Translator()),
        );
    }

    public function testPrependsHeaderWithGameIdToText(): void
    {
        $gameId = $this->createGame(title: self::GAME_TITLE);

        $message = UserGameDetailMessageFactory::build($gameId, listPage: 1, translator: new Translator());

        $this->assertNotNull($message);
        $text = $message->getText()->getMessageText();
        $expectedHeader = new MarkdownV2()->bold("Game #$gameId");
        $this->assertStringContainsString($expectedHeader, $text);
        // Header must come BEFORE the title
        $this->assertLessThan(
            strpos($text, '18:00'),
            strpos($text, $expectedHeader),
            'Header should appear before the game title',
        );
    }

    public function testRendersGameTitleAlongsideHeader(): void
    {
        $gameId = $this->createGame(title: self::GAME_TITLE);

        $message = UserGameDetailMessageFactory::build($gameId, listPage: 1, translator: new Translator());

        $this->assertStringContainsString('18:00', $message->getText()->getMessageText());
    }

    public function testKeyboardHasExactlyTwoRows(): void
    {
        $gameId = $this->createGame(title: self::GAME_TITLE);

        $message = UserGameDetailMessageFactory::build($gameId, listPage: 1, translator: new Translator());

        $this->assertCount(2, $this->extractKeyboard($message));
    }

    public function testPastGameKeyboardHasOnlyBackRow(): void
    {
        $gameId = $this->createGame(title: 'Saturday 01.01.2020 18:00');

        $message = UserGameDetailMessageFactory::build($gameId, listPage: 1, translator: new Translator());

        $keyboard = $this->extractKeyboard($message);
        $this->assertCount(1, $keyboard, 'Past game should expose only Back; no Share row');
        $this->assertStringContainsString('Back', $keyboard[0][0]['text']);
    }

    public function testPastGameKeyboardOmitsShareButton(): void
    {
        $gameId = $this->createGame(title: 'Saturday 01.01.2020 18:00');

        $message = UserGameDetailMessageFactory::build($gameId, listPage: 1, translator: new Translator());

        $keyboard = $this->extractKeyboard($message);
        $this->assertArrayNotHasKey('switch_inline_query', $keyboard[0][0]);
    }

    public function testPastGameRendersHeaderNoticeAndBodyWithExpectedSpacing(): void
    {
        $gameId = $this->createGame(title: 'Saturday 01.01.2020 18:00');

        $message = UserGameDetailMessageFactory::build($gameId, listPage: 1, translator: new Translator());

        $this->assertNotNull($message);
        $formatter = new MarkdownV2();
        $header = $formatter->bold("Game #$gameId");
        $notice = $formatter->blockquote($formatter->escape(ShareGameMessageBuilder::DISABLED_NOTICE));
        $body = $formatter->escape('Saturday 01.01.2020 18:00');

        // Single newline between header and notice; triple newline before the body.
        $this->assertStringContainsString($header . "\n" . $notice . "\n\n\n" . $body, $message->getText()->getMessageText());
    }

    public function testFutureGameOmitsSharingDisabledNotice(): void
    {
        $gameId = $this->createGame(title: self::GAME_TITLE);

        $message = UserGameDetailMessageFactory::build($gameId, listPage: 1, translator: new Translator());

        $this->assertNotNull($message);
        $this->assertStringNotContainsString(
            ShareGameMessageBuilder::DISABLED_NOTICE,
            $message->getText()->getMessageText(),
        );
    }

    public function testFirstRowIsShareButton(): void
    {
        $gameId = $this->createGame(title: self::GAME_TITLE);

        $message = UserGameDetailMessageFactory::build($gameId, listPage: 1, translator: new Translator());
        $keyboard = $this->extractKeyboard($message);

        $shareButton = $keyboard[0][0];
        $this->assertSame('Share', $shareButton['text']);
        $this->assertSame("Forward game $gameId", $shareButton['switch_inline_query']);
    }

    public function testSecondRowIsBackButtonReturningToTheGivenListPage(): void
    {
        $gameId = $this->createGame(title: self::GAME_TITLE);

        $message = UserGameDetailMessageFactory::build($gameId, listPage: 4, translator: new Translator());
        $keyboard = $this->extractKeyboard($message);

        $backButton = $keyboard[1][0];
        $this->assertStringContainsString('Back', $backButton['text']);

        $callbackData = UserCallbackData::fromJson($backButton['callback_data']);
        $this->assertNotNull($callbackData);
        $this->assertSame(UserCallbackAction::GamesList, $callbackData->getAction());
        $this->assertSame(4, $callbackData->getPage());
    }

    public function testGameBodyMatchesRealGameBody(): void
    {
        $gameId = $this->createGame(title: self::GAME_TITLE);

        $userMessage = UserGameDetailMessageFactory::build($gameId, listPage: 1, translator: new Translator());
        $realMessage = GameFactory::fromGameId($gameId)->buildTelegramMessage();

        $this->assertNotNull($userMessage);
        // Stripping the prepended header should yield the same body as the real game view.
        $realText = $realMessage->getText()->getMessageText();
        $userText = $userMessage->getText()->getMessageText();
        $this->assertStringEndsWith($realText, $userText);
    }

    private function extractKeyboard(?TelegramMessage $message): array
    {
        $this->assertNotNull($message);

        return json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard'];
    }
}
