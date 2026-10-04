<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\RestrictedActionCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\RoleGateProcessor;
use BeachVolleybot\Processors\AdminProcessors\Root\Log\RootLogsListCallbackProcessor;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\AdminCallbackQueryHandler;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\AbstractMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserManager;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminCallbackQueryHandlerTest extends ProcessorTestCase
{
    private const string RESTRICTED = 'Access restricted';

    private AdminCallbackQueryHandler $handler;

    public function testMatchesAnAdminCallbackFromAnyoneWithoutTouchingTheDatabase(): void
    {
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload('{"aa":"lgs"}'));

        $this->assertTrue($this->handler->matches($update));
        $this->assertNull(new UserManager()->findUserRecordById(self::ADMIN_TELEGRAM_USER_ID));
    }

    public function testDoesNotMatchAnAdminCallbackOutsideAPrivateChat(): void
    {
        $payload = $this->adminCallbackQueryPayload('{"aa":"lgs"}');
        $payload['callback_query']['message']['chat'] = ['id' => -100, 'title' => 'Beach', 'type' => 'supergroup'];

        $this->assertFalse($this->handler->matches(TelegramUpdate::fromArray($payload)));
    }

    public function testDoesNotMatchAnAdminCallbackOnAnInlineMessage(): void
    {
        $update = TelegramUpdate::fromArray($this->callbackQueryPayload('inline_1', '{"aa":"lgs"}'));

        $this->assertFalse($this->handler->matches($update));
    }

    public function testGatesTheActionsProcessorOnItsRequiredRole(): void
    {
        $this->seedAdmin();
        $callbackData = AdminCallbackData::create(AdminCallbackAction::Logs);
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData->toJson()));

        $processor = $this->handler->createProcessor($this->telegramSender, $update);
        $sender = new UserManager()->findUserRecordById(self::ADMIN_TELEGRAM_USER_ID);

        $this->assertEquals(
            new RoleGateProcessor(
                $this->telegramSender,
                $sender,
                Role::Root,
                new RootLogsListCallbackProcessor($this->telegramSender, $callbackData, $sender),
                new RestrictedActionCallbackProcessor($this->telegramSender, $callbackData, $sender),
            ),
            $processor,
        );
    }

    public function testAdminsSettingsPressCostsOneQuery(): void
    {
        $this->seedAdmin();

        $queries = $this->queriesDuring(fn() => $this->processThroughHandler('{"aa":"st"}'));

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO users', $queries[0]);
    }

    public function testRestrictedPressCostsOneQuery(): void
    {
        $this->seedAdmin();

        $queries = $this->queriesDuring(fn() => $this->processThroughHandler('{"aa":"lgs"}'));

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO users', $queries[0]);
    }

    public function testRootOpensTheRootOnlyLogs(): void
    {
        $this->seedRoot();

        $this->processThroughHandler('{"aa":"lgs"}');

        $this->assertMessageEdited();
        $this->assertAnsweredWith('');
    }

    public function testAdminIsRestrictedFromTheRootOnlyLogsAndMovedBackToSettings(): void
    {
        $this->seedAdmin();

        $this->processThroughHandler('{"aa":"lgs"}');

        $this->assertAnsweredWith(self::RESTRICTED);
        $this->assertStringContainsString('Admin panel', $this->editedText());
    }

    public function testRootOpensAUsersNotifications(): void
    {
        $this->seedRoot();
        $this->seedOptedInUser();

        $this->processThroughHandler('{"aa":"nl","u":300}');

        $this->assertStringContainsString("*Notifications*\nUser: Alice\n", $this->editedText());
        $this->assertAnsweredWith('');
    }

    public function testAdminIsRestrictedFromSwitchingAUsersNotification(): void
    {
        $this->seedAdmin();
        $this->seedOptedInUser();

        $this->processThroughHandler('{"aa":"ne","u":300,"n":3}');

        $this->assertAnsweredWith(self::RESTRICTED);
        $this->assertSame(0, new UserManager()->findUserRecordById(300)->notifications?->toInt());
    }

    private function seedOptedInUser(): void
    {
        $this->createUser(300, 'Alice');
        $this->db->update('users', ['notifications' => 0], ['telegram_user_id' => 300]);
    }

    public function testAdminOpensTheGamesList(): void
    {
        $this->seedAdmin();

        $this->processThroughHandler('{"aa":"gl"}');

        $this->assertMessageEdited();
        $this->assertAnsweredWith('');
    }

    public function testAdminOpensAGameWithOnlyShareAndBack(): void
    {
        $this->seedAdmin();
        $gameId = $this->seedUpcomingGameWithLocation();

        $this->processThroughHandler(AdminCallbackData::create(AdminCallbackAction::GameDetail)->withGameId($gameId)->toJson());

        $this->assertSame(['Share', AbstractMessageBuilder::LABEL_BACK], $this->lastKeyboardLabels('editMessageText'));
        $this->assertAnsweredWith('');
    }

    public function testRootOpensAGameWithEveryButton(): void
    {
        $this->seedRoot();
        $gameId = $this->seedUpcomingGameWithLocation();

        $this->processThroughHandler(AdminCallbackData::create(AdminCallbackAction::GameDetail)->withGameId($gameId)->toJson());

        $this->assertSame(
            ['Share', 'Users', 'Remove Location', AbstractMessageBuilder::LABEL_BACK],
            $this->lastKeyboardLabels('editMessageText'),
        );
    }

    public static function gameMutationActions(): iterable
    {
        yield 'game users' => [AdminCallbackAction::GameUsers];
        yield 'user settings' => [AdminCallbackAction::UserSettings];
        yield 'remove slot' => [AdminCallbackAction::RemoveSlot];
        yield 'add slot' => [AdminCallbackAction::AddSlot];
        yield 'remove location' => [AdminCallbackAction::RemoveLocation];
        yield 'add net' => [AdminCallbackAction::AddNet];
        yield 'remove net' => [AdminCallbackAction::RemoveNet];
        yield 'add volleyball' => [AdminCallbackAction::AddVolleyball];
        yield 'remove volleyball' => [AdminCallbackAction::RemoveVolleyball];
    }

    #[DataProvider('gameMutationActions')]
    public function testAdminIsRestrictedFromChangingAGameAndNothingChanges(AdminCallbackAction $action): void
    {
        $this->seedAdmin();
        $gameId = $this->seedUpcomingGameWithLocation(net: 1, volleyball: 1);
        $gameStateBefore = $this->gameState();

        $this->processThroughHandler($this->gameUserCallbackData($action, $gameId));

        $this->assertAnsweredWith(self::RESTRICTED);
        $this->assertStringContainsString('Admin panel', $this->editedText());
        $this->assertSame($gameStateBefore, $this->gameState());
        $editCalls = array_filter($this->bot->calls, fn(array $call) => 'editMessageText' === $call['method']);
        $this->assertCount(1, $editCalls, 'Only the settings panel is edited, no game message is refreshed');
    }

    public function testRootStillChangesAGame(): void
    {
        $this->seedRoot();
        $gameId = $this->seedUpcomingGameWithLocation();

        $this->processThroughHandler($this->gameUserCallbackData(AdminCallbackAction::AddNet, $gameId));

        $this->assertSame(1, new GameUserManager()->findGameUserRecord($gameId, 200)->net);
        $this->assertAnsweredWith('Added');
    }

    private function seedUpcomingGameWithLocation(int $net = 0, int $volleyball = 0): int
    {
        $gameId = $this->createGame(title: 'Game 31.12.2099 18:00');
        $this->db->update('games', ['location' => '55.7,37.6'], ['game_id' => $gameId]);
        $this->createUser(200, 'Alice');
        $this->db->insert('game_users', [
            'game_id' => $gameId,
            'telegram_user_id' => 200,
            'time' => '18:00',
            'volleyball' => $volleyball,
            'net' => $net,
        ]);
        $this->createSlot($gameId, 200, 1);

        return $gameId;
    }

    private function gameUserCallbackData(AdminCallbackAction $action, int $gameId): string
    {
        return AdminCallbackData::create($action)->withGameId($gameId)->withUserId(200)->withPage(1)->toJson();
    }

    private function gameState(): array
    {
        return [
            $this->db->select('games', '*'),
            $this->db->select('game_users', '*'),
            $this->db->select('game_slots', '*'),
        ];
    }

    public function testPlayerPressingAnAdminButtonTurnsThePanelIntoTheRestrictedMessage(): void
    {
        $this->processThroughHandler('{"aa":"st"}');

        $this->assertAnsweredWith(self::RESTRICTED);
        $this->assertSame('*' . self::RESTRICTED . '*', $this->editedText());
        $this->assertSame([], $this->lastKeyboard('editMessageText'));
        $editCall = array_find($this->bot->calls, fn(array $call) => 'editMessageText' === $call['method']);
        $this->assertSame([self::ADMIN_TELEGRAM_USER_ID, 109], array_slice($editCall['args'], 0, 2));
        $this->assertMessageNotDeleted();
    }

    private function processThroughHandler(string $callbackData): void
    {
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload($callbackData));

        $this->handler->createProcessor($this->telegramSender, $update)->process($update);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new AdminCallbackQueryHandler();
    }
}
