<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\CallbackData;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\UpdateProcessors\GameCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use PHPUnit\Framework\TestCase;

final class UserCallbackDataTest extends TestCase
{
    // --- create + toJson ---

    public function testCreateActionOnly(): void
    {
        $json = UserCallbackData::create(UserCallbackAction::GamesList)->toJson();

        $this->assertSame('{"ua":"ugl"}', $json);
    }

    public function testCreateWithGameId(): void
    {
        $json = UserCallbackData::create(UserCallbackAction::GameDetail)
            ->withGameId(42)
            ->toJson();

        $this->assertSame('{"ua":"ugd","g":42}', $json);
    }

    public function testCreateWithGameIdAndPage(): void
    {
        $json = UserCallbackData::create(UserCallbackAction::GameDetail)
            ->withGameId(42)
            ->withPage(3)
            ->toJson();

        $this->assertSame('{"ua":"ugd","g":42,"p":3}', $json);
    }

    // --- fromJson recognizes user vs other namespaces ---

    public function testFromJsonRecognizesUserData(): void
    {
        $this->assertNotNull(UserCallbackData::fromJson('{"ua":"ugl"}'));
    }

    public function testFromJsonRejectsAdminData(): void
    {
        $this->assertNull(UserCallbackData::fromJson('{"aa":"gl"}'));
    }

    public function testFromJsonRejectsGameData(): void
    {
        $this->assertNull(UserCallbackData::fromJson('{"a":"j"}'));
    }

    public function testFromJsonRejectsNull(): void
    {
        $this->assertNull(UserCallbackData::fromJson(null));
    }

    // --- fromJson + getters ---

    public function testFromJsonReturnsCorrectAction(): void
    {
        $callbackData = UserCallbackData::fromJson('{"ua":"ugd","g":42}');

        $this->assertSame(UserCallbackAction::GameDetail, $callbackData->getAction());
    }

    public function testGetGameIdReturnsValue(): void
    {
        $callbackData = UserCallbackData::fromJson('{"ua":"ugd","g":42}');

        $this->assertSame(42, $callbackData->getGameId());
    }

    public function testGetGameIdReturnsNullWhenAbsent(): void
    {
        $callbackData = UserCallbackData::fromJson('{"ua":"ugl"}');

        $this->assertNull($callbackData->getGameId());
    }

    public function testGetPageReturnsValue(): void
    {
        $callbackData = UserCallbackData::fromJson('{"ua":"ugl","p":3}');

        $this->assertSame(3, $callbackData->getPage());
    }

    public function testGetPageDefaultsToOneWhenAbsent(): void
    {
        $callbackData = UserCallbackData::fromJson('{"ua":"ugl"}');

        $this->assertSame(1, $callbackData->getPage());
    }

    // --- withPage / withGameId ---

    public function testWithPageReturnsNewInstance(): void
    {
        $original = UserCallbackData::create(UserCallbackAction::GamesList);
        $withPage = $original->withPage(3);

        $this->assertSame(1, $original->getPage());
        $this->assertSame(3, $withPage->getPage());
    }

    public function testWithPagePreservesOtherParams(): void
    {
        $original = UserCallbackData::create(UserCallbackAction::GameDetail)->withGameId(42);
        $withPage = $original->withPage(2);

        $this->assertSame(42, $withPage->getGameId());
        $this->assertSame(UserCallbackAction::GameDetail, $withPage->getAction());
        $this->assertSame(2, $withPage->getPage());
    }

    public function testWithGameIdPreservesOtherParams(): void
    {
        $original = UserCallbackData::create(UserCallbackAction::GameDetail)->withPage(4);
        $withGameId = $original->withGameId(99);

        $this->assertSame(4, $withGameId->getPage());
        $this->assertSame(99, $withGameId->getGameId());
        $this->assertSame(UserCallbackAction::GameDetail, $withGameId->getAction());
    }

    // --- roundtrip ---

    public function testCreateAndParseRoundtrip(): void
    {
        $json = UserCallbackData::create(UserCallbackAction::GameDetail)
            ->withGameId(42)
            ->withPage(7)
            ->toJson();

        $parsed = UserCallbackData::fromJson($json);

        $this->assertSame(UserCallbackAction::GameDetail, $parsed->getAction());
        $this->assertSame(42, $parsed->getGameId());
        $this->assertSame(7, $parsed->getPage());
    }

    // --- no intersection with other callback namespaces ---

    public function testUserCallbackNotRecognizedAsAdminCallback(): void
    {
        foreach (UserCallbackAction::cases() as $action) {
            $json = UserCallbackData::create($action)->toJson();

            $this->assertNull(AdminCallbackData::fromJson($json), "User action '{$action->value}' was recognized as an admin callback");
        }
    }

    public function testUserCallbackNotRecognizedAsGameCallback(): void
    {
        foreach (UserCallbackAction::cases() as $action) {
            $json = UserCallbackData::create($action)->toJson();

            $this->assertNull(GameCallbackData::fromJson($json), "User action '{$action->value}' was recognized as a game callback");
        }
    }

    public function testAdminCallbackNotRecognizedAsUserCallback(): void
    {
        foreach (AdminCallbackAction::cases() as $action) {
            $json = AdminCallbackData::create($action)->toJson();

            $this->assertNull(UserCallbackData::fromJson($json), "Admin action '{$action->value}' was recognized as a user callback");
        }
    }

    public function testGameCallbackNotRecognizedAsUserCallback(): void
    {
        foreach (GameCallbackAction::cases() as $action) {
            $json = GameCallbackData::create($action)->toJson();

            $this->assertNull(UserCallbackData::fromJson($json), "Game action '{$action->value}' was recognized as a user callback");
        }
    }

    // --- 64-byte limit ---

    public function testCallbackDataFitsWithin64Bytes(): void
    {
        $cases = [
            UserCallbackData::create(UserCallbackAction::GamesList)->toJson(),
            UserCallbackData::create(UserCallbackAction::GamesList)->withPage(999)->toJson(),
            UserCallbackData::create(UserCallbackAction::GameDetail)->withGameId(99999)->toJson(),
            UserCallbackData::create(UserCallbackAction::GameDetail)->withGameId(99999)->withPage(999)->toJson(),
        ];

        foreach ($cases as $json) {
            $this->assertLessThanOrEqual(64, strlen($json), "Callback data exceeds 64 bytes: $json");
        }
    }
}
