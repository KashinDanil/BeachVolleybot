<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\CallbackData;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use PHPUnit\Framework\TestCase;

final class AdminCallbackDataTest extends TestCase
{
    // --- create + toJson ---

    public function testCreateActionOnly(): void
    {
        $json = AdminCallbackData::create(AdminCallbackAction::Logs)->toJson();

        $this->assertSame('{"aa":"lgs"}', $json);
    }

    public function testCreateWithGameId(): void
    {
        $json = AdminCallbackData::create(AdminCallbackAction::GameDetail)
            ->withGameId(42)
            ->toJson();

        $this->assertSame('{"aa":"gd","g":42}', $json);
    }

    public function testCreateWithMultipleParams(): void
    {
        $json = AdminCallbackData::create(AdminCallbackAction::PlayerSettings)
            ->withGameId(42)
            ->withUserId(12345678)
            ->toJson();

        $this->assertSame('{"aa":"ps","g":42,"u":12345678}', $json);
    }

    // --- fromJson recognizes admin vs non-admin ---

    public function testFromJsonRecognizesAdminData(): void
    {
        $this->assertNotNull(AdminCallbackData::fromJson('{"aa":"lgs"}'));
    }

    public function testFromJsonRejectsGameData(): void
    {
        $this->assertNull(AdminCallbackData::fromJson('{"a":"j"}'));
    }

    public function testFromJsonRejectsNull(): void
    {
        $this->assertNull(AdminCallbackData::fromJson(null));
    }

    // --- fromJson + getAction ---

    public function testFromJsonReturnsCorrectAction(): void
    {
        $callbackData = AdminCallbackData::fromJson('{"aa":"lgs"}');

        $this->assertSame(AdminCallbackAction::Logs, $callbackData->getAction());
    }

    // --- getters ---

    public function testGetGameIdReturnsValue(): void
    {
        $callbackData = AdminCallbackData::fromJson('{"aa":"gd","g":42}');

        $this->assertSame(42, $callbackData->getGameId());
    }

    public function testGetGameIdReturnsNullWhenAbsent(): void
    {
        $callbackData = AdminCallbackData::fromJson('{"aa":"lgs"}');

        $this->assertNull($callbackData->getGameId());
    }

    public function testGetUserIdReturnsValue(): void
    {
        $callbackData = AdminCallbackData::fromJson('{"aa":"ps","g":42,"u":12345678}');

        $this->assertSame(12345678, $callbackData->getUserId());
    }

    public function testGetUserIdReturnsNullWhenAbsent(): void
    {
        $callbackData = AdminCallbackData::fromJson('{"aa":"gd","g":42}');

        $this->assertNull($callbackData->getUserId());
    }

    public function testGetPageReturnsValue(): void
    {
        $callbackData = AdminCallbackData::fromJson('{"aa":"gl","p":3}');

        $this->assertSame(3, $callbackData->getPage());
    }

    public function testGetPageDefaultsToOneWhenAbsent(): void
    {
        $callbackData = AdminCallbackData::fromJson('{"aa":"lgs"}');

        $this->assertSame(1, $callbackData->getPage());
    }

    public function testGetFilenameReturnsValue(): void
    {
        $callbackData = AdminCallbackData::fromJson('{"aa":"lf","f":"app.log"}');

        $this->assertSame('app.log', $callbackData->getFilename());
    }

    public function testGetFilenameReturnsNullWhenAbsent(): void
    {
        $callbackData = AdminCallbackData::fromJson('{"aa":"lgs"}');

        $this->assertNull($callbackData->getFilename());
    }

    // --- withPage ---

    public function testWithPageReturnsNewInstance(): void
    {
        $original = AdminCallbackData::create(AdminCallbackAction::GamesList);
        $withPage = $original->withPage(3);

        $this->assertSame(1, $original->getPage());
        $this->assertSame(3, $withPage->getPage());
    }

    public function testWithPagePreservesOtherParams(): void
    {
        $original = AdminCallbackData::create(AdminCallbackAction::GamePlayers)->withGameId(42);
        $withPage = $original->withPage(2);

        $this->assertSame(42, $withPage->getGameId());
        $this->assertSame(AdminCallbackAction::GamePlayers, $withPage->getAction());
        $this->assertSame(2, $withPage->getPage());
    }

    // --- roundtrip ---

    public function testCreateAndParseRoundtrip(): void
    {
        $json = AdminCallbackData::create(AdminCallbackAction::PlayerSettings)
            ->withGameId(42)
            ->withUserId(100)
            ->toJson();

        $parsed = AdminCallbackData::fromJson($json);

        $this->assertSame(AdminCallbackAction::PlayerSettings, $parsed->getAction());
        $this->assertSame(42, $parsed->getGameId());
        $this->assertSame(100, $parsed->getUserId());
    }

    // --- no intersection with CallbackData ---

    public function testAdminCallbackNotRecognizedAsGameCallback(): void
    {
        foreach (AdminCallbackAction::cases() as $action) {
            $json = AdminCallbackData::create($action)->toJson();

            $this->assertNull(CallbackData::extractAction($json), "Admin action '{$action->value}' was recognized as a game callback");
        }
    }

    public function testGameCallbackNotRecognizedAsAdminCallback(): void
    {
        foreach (CallbackAction::cases() as $action) {
            $json = CallbackData::encode($action);

            $this->assertNull(AdminCallbackData::fromJson($json), "Game action '{$action->value}' was recognized as an admin callback");
        }
    }

    // --- 64-byte limit ---

    public function testCallbackDataFitsWithin64Bytes(): void
    {
        $cases = [
            AdminCallbackData::create(AdminCallbackAction::Settings)->toJson(),
            AdminCallbackData::create(AdminCallbackAction::Logs)->toJson(),
            AdminCallbackData::create(AdminCallbackAction::LogFile)->withFilename('user_actions.log')->toJson(),
            AdminCallbackData::create(AdminCallbackAction::GamesList)->withPage(999)->toJson(),
            AdminCallbackData::create(AdminCallbackAction::GameDetail)->withGameId(99999)->toJson(),
            AdminCallbackData::create(AdminCallbackAction::PlayerSettings)->withGameId(99999)->withUserId(12345678)->toJson(),
            AdminCallbackData::create(AdminCallbackAction::RemoveSlot)->withGameId(99999)->withUserId(12345678)->toJson(),
            AdminCallbackData::create(AdminCallbackAction::AddVolleyball)->withGameId(99999)->withUserId(12345678)->toJson(),
        ];

        foreach ($cases as $json) {
            $this->assertLessThanOrEqual(64, strlen($json), "Callback data exceeds 64 bytes: $json");
        }
    }
}
