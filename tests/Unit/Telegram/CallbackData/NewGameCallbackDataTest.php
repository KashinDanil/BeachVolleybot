<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\CallbackData;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\UpdateProcessors\GameCallbackAction;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use PHPUnit\Framework\TestCase;

final class NewGameCallbackDataTest extends TestCase
{
    // --- create + toJson ---

    public function testCreateActionOnly(): void
    {
        $json = NewGameCallbackData::create(NewGameCallbackAction::ShowDatePage)->withPage(2)->toJson();

        $this->assertSame('{"na":"dp","p":2}', $json);
    }

    public function testPickDateCarriesDate(): void
    {
        $json = NewGameCallbackData::create(NewGameCallbackAction::PickDate)->withDate('2099-12-31')->toJson();

        $this->assertSame('{"na":"d","d":"2099-12-31"}', $json);
    }

    public function testPickTimeCarriesTime(): void
    {
        $json = NewGameCallbackData::create(NewGameCallbackAction::PickTime)->withTime('06:15')->toJson();

        $this->assertSame('{"na":"t","t":"06:15"}', $json);
    }

    public function testPickVenueCarriesName(): void
    {
        $json = NewGameCallbackData::create(NewGameCallbackAction::PickVenue)->withVenueName('Bogatell')->toJson();

        $this->assertSame('{"na":"v","v":"Bogatell"}', $json);
    }

    // --- fromJson + getters ---

    public function testRoundtrip(): void
    {
        $json = NewGameCallbackData::create(NewGameCallbackAction::PickVenue)
            ->withDate('2099-12-31')
            ->withTime('18:30')
            ->withVenueName('Nova Mar Bella')
            ->withPage(3)
            ->toJson();

        $parsed = NewGameCallbackData::fromJson($json);

        $this->assertSame(NewGameCallbackAction::PickVenue, $parsed->getAction());
        $this->assertSame('2099-12-31', $parsed->getDate());
        $this->assertSame('18:30', $parsed->getTime());
        $this->assertSame('Nova Mar Bella', $parsed->getVenueName());
        $this->assertSame(3, $parsed->getPage());
    }

    public function testGetPageDefaultsToOne(): void
    {
        $parsed = NewGameCallbackData::fromJson('{"na":"dp"}');

        $this->assertSame(1, $parsed->getPage());
    }

    public function testMissingFieldsAreNull(): void
    {
        $parsed = NewGameCallbackData::fromJson('{"na":"v","v":"Bogatell"}');

        $this->assertNull($parsed->getDate());
        $this->assertNull($parsed->getTime());
        $this->assertSame('Bogatell', $parsed->getVenueName());
    }

    public function testWithersReturnNewInstances(): void
    {
        $original = NewGameCallbackData::create(NewGameCallbackAction::ShowVenuePage);
        $paged = $original->withPage(4);

        $this->assertSame(1, $original->getPage());
        $this->assertSame(4, $paged->getPage());
    }

    // --- mutual exclusivity with other callback namespaces ---

    public function testFromJsonRejectsNull(): void
    {
        $this->assertNull(NewGameCallbackData::fromJson(null));
    }

    public function testFromJsonRejectsMalformedJson(): void
    {
        $this->assertNull(NewGameCallbackData::fromJson('not json'));
    }

    public function testFromJsonRejectsNonStringAction(): void
    {
        $this->assertNull(NewGameCallbackData::fromJson('{"na":5}'));
    }

    public function testFromJsonNullsWrongTypeFieldsInsteadOfThrowing(): void
    {
        $parsed = NewGameCallbackData::fromJson('{"na":"v","v":123,"d":123,"p":"x"}');

        $this->assertNotNull($parsed);
        $this->assertNull($parsed->getVenueName());
        $this->assertNull($parsed->getDate());
        $this->assertSame(1, $parsed->getPage());
    }

    public function testNewGameNotRecognizedByOtherNamespaces(): void
    {
        foreach (NewGameCallbackAction::cases() as $action) {
            $json = NewGameCallbackData::create($action)->toJson();

            $this->assertNull(UserCallbackData::fromJson($json), "New game action '{$action->value}' recognized as user callback");
            $this->assertNull(AdminCallbackData::fromJson($json), "New game action '{$action->value}' recognized as admin callback");
            $this->assertNull(GameCallbackData::fromJson($json), "New game action '{$action->value}' recognized as game callback");
        }
    }

    public function testOtherNamespacesNotRecognizedAsNewGame(): void
    {
        foreach (UserCallbackAction::cases() as $action) {
            $this->assertNull(NewGameCallbackData::fromJson(UserCallbackData::create($action)->toJson()));
        }

        foreach (AdminCallbackAction::cases() as $action) {
            $this->assertNull(NewGameCallbackData::fromJson(AdminCallbackData::create($action)->toJson()));
        }

        foreach (GameCallbackAction::cases() as $action) {
            $this->assertNull(NewGameCallbackData::fromJson(GameCallbackData::create($action)->toJson()));
        }
    }

    // --- 64-byte limit ---

    public function testCallbackDataFitsWithin64Bytes(): void
    {
        $cases = [
            NewGameCallbackData::create(NewGameCallbackAction::ShowDatePage)->withPage(4)->toJson(),
            NewGameCallbackData::create(NewGameCallbackAction::PickDate)->withDate('2099-12-31')->toJson(),
            NewGameCallbackData::create(NewGameCallbackAction::PickTime)->withTime('23:45')->toJson(),
            NewGameCallbackData::create(NewGameCallbackAction::PickVenue)->withVenueName('Nova Mar Bella')->toJson(),
        ];

        foreach ($cases as $json) {
            $this->assertLessThanOrEqual(64, strlen($json), "Callback data exceeds 64 bytes: $json");
        }
    }
}
