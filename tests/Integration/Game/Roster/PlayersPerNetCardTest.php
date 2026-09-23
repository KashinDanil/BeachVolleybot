<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game\Roster;

use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\NewGame\NewGameFormText;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Telegram\PlainText;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use DateTimeImmutable;

final class PlayersPerNetCardTest extends ProcessorTestCase
{
    private const string DIVIDER = '———';

    private GameManager $gameManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gameManager = new GameManager();
    }

    // --- No limit ---

    public function testNoSettingRendersUnchanged(): void
    {
        $gameId = $this->seedFullGame();
        $this->seedPlayer($gameId, 200, position: 1, firstName: 'Alice');
        $this->seedPlayer($gameId, 201, position: 2, firstName: 'Bob');

        $text = $this->cardText($gameId);

        $this->assertStringNotContainsString(self::DIVIDER, $text);
        $this->assertStringContainsString('1\. Alice', $text);
        $this->assertStringContainsString('2\. Bob', $text);
    }

    public function testSettingButNoNetRendersUnchanged(): void
    {
        $gameId = $this->seedFullGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));
        $this->seedPlayer($gameId, 200, position: 1, firstName: 'Alice');
        $this->seedPlayer($gameId, 201, position: 2, firstName: 'Bob');

        $text = $this->cardText($gameId);

        $this->assertStringNotContainsString(self::DIVIDER, $text);
    }

    // --- Promotion ---

    public function testNetByFirstSignerAddsDividerWithoutPromotion(): void
    {
        $gameId = $this->seedFullGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));
        $this->seedPlayer($gameId, 200, position: 1, firstName: 'Alice', net: 1);
        $this->seedPlayer($gameId, 201, position: 2, firstName: 'Bob');
        $this->seedPlayer($gameId, 202, position: 3, firstName: 'Carol');
        $this->seedPlayer($gameId, 203, position: 4, firstName: 'Dave');
        $this->seedPlayer($gameId, 204, position: 5, firstName: 'Erin');

        $lines = $this->cardLines($gameId);

        $this->assertStringContainsString('1\. Alice', $lines[0]);
        $this->assertSame(self::DIVIDER, $lines[4]);
        $this->assertStringContainsString('Erin', $lines[5]);
    }

    /** Boundary case: the bringer's own place (4) already sits inside a limit of 4, so nobody moves. */
    public function testNetBringerExactlyAtTheLimitKeepsTheirPlace(): void
    {
        $gameId = $this->seedFullGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));
        $this->seedPlayer($gameId, 200, position: 1, firstName: 'Alice');
        $this->seedPlayer($gameId, 201, position: 2, firstName: 'Bob');
        $this->seedPlayer($gameId, 202, position: 3, firstName: 'Carol');
        $this->seedPlayer($gameId, 203, position: 4, firstName: 'Dave', net: 1);
        $this->seedPlayer($gameId, 204, position: 5, firstName: 'Erin');

        $lines = $this->cardLines($gameId);

        $this->assertStringContainsString('4\. Dave', $lines[3]);
        $this->assertSame(self::DIVIDER, $lines[4]);
        $this->assertStringContainsString('Erin', $lines[5]);
    }

    public function testNetByLastSignerPromotesToTop(): void
    {
        $gameId = $this->seedFullGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));
        $this->seedPlayer($gameId, 200, position: 1, firstName: 'Alice', volleyball: 0);
        $this->seedPlayer($gameId, 201, position: 2, firstName: 'Bob', volleyball: 0);
        $this->seedPlayer($gameId, 202, position: 3, firstName: 'Carol', volleyball: 0);
        $this->seedPlayer($gameId, 203, position: 4, firstName: 'Dave', volleyball: 0);
        $this->seedPlayer($gameId, 204, position: 5, firstName: 'Erin', volleyball: 0);
        $this->seedPlayer($gameId, 205, position: 6, firstName: 'Frank', volleyball: 0);
        $this->seedPlayer($gameId, 206, position: 7, firstName: 'Grace', net: 1);

        $lines = $this->cardLines($gameId);

        $this->assertStringContainsString('1\. Grace', $lines[0]);
        $this->assertStringContainsString('2\. Alice', $lines[1]);
        $this->assertStringContainsString('4\. Carol', $lines[3]);
        $this->assertSame(self::DIVIDER, $lines[4]);
        $this->assertStringContainsString('Frank', $lines[7]);
    }

    /**
     * The same roster with everybody also holding a ball: Alice's covers Grace's net, so both are
     * carriers and both come up — Alice keeping the place she signed up for.
     */
    public function testBallHolderKeepsTheirPlaceAheadOfThePromotedNetBringer(): void
    {
        $gameId = $this->seedFullGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));
        $this->seedPlayer($gameId, 200, position: 1, firstName: 'Alice');
        $this->seedPlayer($gameId, 201, position: 2, firstName: 'Bob');
        $this->seedPlayer($gameId, 202, position: 3, firstName: 'Carol');
        $this->seedPlayer($gameId, 203, position: 4, firstName: 'Dave');
        $this->seedPlayer($gameId, 204, position: 5, firstName: 'Erin');
        $this->seedPlayer($gameId, 205, position: 6, firstName: 'Frank');
        $this->seedPlayer($gameId, 206, position: 7, firstName: 'Grace', net: 1);

        $lines = $this->cardLines($gameId);

        $this->assertStringContainsString('1\. Alice', $lines[0]);
        $this->assertStringContainsString('2\. Grace', $lines[1]);
        $this->assertSame(self::DIVIDER, $lines[4]);
        $this->assertStringContainsString('Frank', $lines[7]);
    }

    /**
     * Row 5→6 of the plan's worked table: Bob already holds a slot, so tapping +🕸️ on him
     * only raises the limit — it does not move a slot. Also proves reversibility: -🕸️ brings
     * the card back to the exact text it had before, since it only decrements the net.
     */
    public function testSecondNetFromAnotherUserSlidesTheDividerDownAndBackReversibly(): void
    {
        $gameId = $this->seedFullGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));
        $this->seedPlayer($gameId, 200, position: 1, firstName: 'Bob');
        $this->seedPlayer($gameId, 201, position: 2, firstName: 'Carol');
        $this->seedPlayer($gameId, 202, position: 3, firstName: 'Dave');
        $this->seedPlayer($gameId, 203, position: 4, firstName: 'Alice', net: 1);
        $this->seedPlayer($gameId, 203, position: 5, firstName: 'Alice', net: 1);
        $this->seedPlayer($gameId, 203, position: 6, firstName: 'Alice', net: 1);
        $this->seedPlayer($gameId, 203, position: 7, firstName: 'Alice', net: 1);

        $before = $this->cardText($gameId);
        $beforeLines = $this->cardLines($gameId);
        $this->assertStringContainsString('4\. Alice', $beforeLines[3]);
        $this->assertSame(self::DIVIDER, $beforeLines[4]);
        $this->assertStringContainsString('5\-7\. \+3 \(Alice\)', $beforeLines[5]);

        $this->gameManager->addNet($gameId, 200, 'Bob', null, null);
        $afterAddingNet = $this->cardText($gameId);

        $this->assertStringNotContainsString(self::DIVIDER, $afterAddingNet);
        $this->assertStringContainsString('4\-7\. Alice', $afterAddingNet);

        $this->gameManager->removeNet($gameId, 200);
        $afterRemovingNet = $this->cardText($gameId);

        $this->assertSame($before, $afterRemovingNet);
    }

    // --- Two-digit numbers and ranges ---

    public function testTwoDigitNumbersAndRangeSurviveTheDivider(): void
    {
        $gameId = $this->seedFullGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));
        $this->seedPlayer($gameId, 200, position: 1, firstName: 'Alice', net: 1);
        $this->seedPlayer($gameId, 201, position: 2, firstName: 'Bob', net: 1);
        $this->seedPlayer($gameId, 202, position: 3, firstName: 'Carol', net: 1);
        $this->seedPlayer($gameId, 203, position: 4, firstName: 'Dave');
        $this->seedPlayer($gameId, 204, position: 5, firstName: 'Erin');
        $this->seedPlayer($gameId, 205, position: 6, firstName: 'Frank');
        $this->seedPlayer($gameId, 206, position: 7, firstName: 'Grant');
        $this->seedPlayer($gameId, 207, position: 8, firstName: 'Heidi');
        $this->seedPlayer($gameId, 208, position: 9, firstName: 'Ivan');
        $this->seedPlayer($gameId, 209, position: 10, firstName: 'Judy');
        $this->seedPlayer($gameId, 210, position: 11, firstName: 'Kevin');
        $this->seedPlayer($gameId, 211, position: 12, firstName: 'Liam');
        $this->seedPlayer($gameId, 212, position: 13, firstName: 'Mia');
        $this->seedPlayer($gameId, 212, position: 14, firstName: 'Mia');
        $this->seedPlayer($gameId, 212, position: 15, firstName: 'Mia');

        $text = $this->cardText($gameId);

        $this->assertStringContainsString('12\. Liam', $text);
        $this->assertStringContainsString(self::DIVIDER, $text);
        $this->assertStringContainsString('13\-15\. Mia', $text);
        $this->assertStringNotContainsString('13\. Mia', $text);
    }

    // --- Equipment across the divider ---

    public function testEquipmentOnTheRightSideWhenOnePersonsPlacesStraddleTheDivider(): void
    {
        $gameId = $this->seedFullGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));
        $this->seedPlayer($gameId, 200, position: 1, firstName: 'Alice');
        $this->seedPlayer($gameId, 201, position: 2, firstName: 'Bob', net: 1);
        $this->seedPlayer($gameId, 201, position: 3, firstName: 'Bob', net: 1);
        $this->seedPlayer($gameId, 201, position: 4, firstName: 'Bob', net: 1);
        $this->seedPlayer($gameId, 201, position: 5, firstName: 'Bob', net: 1);
        $this->seedPlayer($gameId, 201, position: 6, firstName: 'Bob', net: 1);

        $lines = $this->cardLines($gameId);

        $this->assertStringContainsString('2\-4\. Bob', $lines[1]);
        $this->assertStringContainsString('🕸️', $lines[1]);
        $this->assertSame(self::DIVIDER, $lines[2]);
        $this->assertStringContainsString('5\-6\. \+2 \(Bob\)', $lines[3]);
        $this->assertStringNotContainsString('🕸️', $lines[3]);
    }

    // --- Reachable end-to-end through the title phrase ---

    /** Proves the feature is reachable by a real user: no setGameSettings shortcut, just the title and joinGame. */
    public function testPlayersPerNetPhraseInTitleDrawsTheDividerAfterJoining(): void
    {
        $gameId = $this->gameManager->createGame(NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Alice'),
            'Beach 18:00, 4 spots per net',
            'query_1',
        ));
        $this->gameManager->addInlineMessage($gameId, 'msg_1', 'query_1');

        $this->gameManager->joinGame($gameId, 201, 'Bob', null, null);
        $this->gameManager->joinGame($gameId, 202, 'Carol', null, null);
        $this->gameManager->joinGame($gameId, 203, 'Dave', null, null);
        $this->gameManager->joinGame($gameId, 204, 'Erin', null, null);

        $lines = $this->cardLines($gameId);

        $this->assertStringContainsString('1\. Alice', $lines[0]);
        $this->assertSame(self::DIVIDER, $lines[4]);
        $this->assertStringContainsString('Erin', $lines[5]);
    }

    /** Proves the wizard's own title builder feeds the same pipeline, not just a hand-typed phrase. */
    public function testWizardBuiltTitleDrawsTheDividerAfterJoining(): void
    {
        $title = new NewGameFormText(new Translator(), new PlainText())
            ->buildGameTitle(new DateTimeImmutable('2099-12-31'), '18:00', 'Beach', 4);

        $gameId = $this->gameManager->createGame(NewGameData::fromUser(
            new TelegramUser(id: 200, firstName: 'Alice'),
            $title,
            'query_wizard',
        ));
        $this->gameManager->addInlineMessage($gameId, 'msg_wizard', 'query_wizard');

        $this->gameManager->joinGame($gameId, 201, 'Bob', null, null);
        $this->gameManager->joinGame($gameId, 202, 'Carol', null, null);
        $this->gameManager->joinGame($gameId, 203, 'Dave', null, null);
        $this->gameManager->joinGame($gameId, 204, 'Erin', null, null);

        $lines = $this->cardLines($gameId);

        $this->assertStringContainsString('1\. Alice', $lines[0]);
        $this->assertSame(self::DIVIDER, $lines[4]);
        $this->assertStringContainsString('Erin', $lines[5]);
    }

    // --- Helpers ---

    private function seedPlayer(
        int $gameId,
        int $telegramUserId,
        int $position,
        string $firstName,
        int $volleyball = 1,
        int $net = 0,
    ): void {
        $this->createUser($telegramUserId, $firstName);

        if (empty($this->db->select('game_users', 'game_id', ['game_id' => $gameId, 'telegram_user_id' => $telegramUserId]))) {
            $this->db->insert('game_users', [
                'game_id' => $gameId,
                'telegram_user_id' => $telegramUserId,
                'time' => '18:00',
                'volleyball' => $volleyball,
                'net' => $net,
            ]);
        }

        $this->createSlot($gameId, $telegramUserId, $position);
    }

    private function cardText(int $gameId): string
    {
        return GameFactory::fromGameId($gameId)->buildTelegramMessage()->getText()->getMessageText();
    }

    /** @return string[] */
    private function cardLines(int $gameId): array
    {
        $sections = explode("\n\n", $this->cardText($gameId));

        return explode("\n", $sections[1]);
    }
}
