<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\User\NotificationType;

final class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository($this->db);
    }

    public function testUpsertInsertsNewUser(): void
    {
        $this->repository->upsert(200, 'Danil', 'Kashin', 'danil_kashin');

        $user = $this->repository->findById(200);

        $this->assertSame('Danil', $user['first_name']);
        $this->assertSame('Kashin', $user['last_name']);
        $this->assertSame('danil_kashin', $user['username']);
    }

    public function testUpsertUpdatesExistingUser(): void
    {
        $this->repository->upsert(200, 'Danil', 'Kashin', 'old_username');
        $this->repository->upsert(200, 'Danil', 'Kashin', 'new_username');

        $user = $this->repository->findById(200);

        $this->assertSame('new_username', $user['username']);
    }

    public function testUpsertStoresTheLanguageCode(): void
    {
        $this->repository->upsert(200, 'Danil', languageCode: 'ru');

        $this->assertSame('ru', $this->repository->findById(200)['language_code']);
    }

    public function testUpsertReplacesTheLanguageCodeWithANewOne(): void
    {
        $this->repository->upsert(200, 'Danil', languageCode: 'ru');
        $this->repository->upsert(200, 'Danil', languageCode: 'es');

        $this->assertSame('es', $this->repository->findById(200)['language_code']);
    }

    public function testUpsertWithoutALanguageCodeKeepsTheKnownOne(): void
    {
        $this->repository->upsert(200, 'Danil', languageCode: 'ru');
        $this->repository->upsert(200, 'Danil');

        $this->assertSame('ru', $this->repository->findById(200)['language_code']);
    }

    public function testUpsertDoesNotCreateDuplicate(): void
    {
        $this->repository->upsert(200, 'Danil');
        $this->repository->upsert(200, 'Danil');

        $this->assertSame(1, $this->repository->countAll());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function testDeleteRemovesUser(): void
    {
        $this->repository->upsert(200, 'Danil');

        $this->assertTrue($this->repository->delete(200));
        $this->assertNull($this->repository->findById(200));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }

    public function testUpdateNotificationsSetsTheStoredInt(): void
    {
        $this->repository->upsert(200, 'Danil');

        $this->repository->updateNotifications(200, NotificationType::PromotedIntoGame->bit());

        $user = $this->repository->findById(200);
        $this->assertSame(NotificationType::PromotedIntoGame->bit(), (int)$user['notifications']);
    }

    public function testUpsertLeavesNotificationsUnset(): void
    {
        $this->repository->upsert(200, 'Danil');

        $this->assertNull($this->repository->findById(200)['notifications']);
    }

    public function testUpsertReturnsTheStoredRow(): void
    {
        $this->repository->upsert(200, 'Danil', languageCode: 'ru');
        $this->repository->updateNotifications(200, NotificationType::PromotedIntoGame->bit());

        $row = $this->repository->upsert(200, 'Daniil', username: 'danil');

        $this->assertSame($this->repository->findById(200), $row);
        $this->assertSame('Daniil', $row['first_name']);
        $this->assertSame('ru', $row['language_code']);
        $this->assertSame(NotificationType::PromotedIntoGame->bit(), $row['notifications']);
    }

    public function testUpsertWithInitialNotificationsInsertsThem(): void
    {
        $this->repository->upsert(200, 'Danil', initialNotifications: 0);

        $this->assertSame(0, $this->repository->findById(200)['notifications']);
    }

    public function testUpsertWithInitialNotificationsFillsUnsetOnes(): void
    {
        $this->repository->upsert(200, 'Danil');

        $this->repository->upsert(200, 'Danil', initialNotifications: 0);

        $this->assertSame(0, $this->repository->findById(200)['notifications']);
    }

    public function testUpsertWithInitialNotificationsKeepsExistingOnes(): void
    {
        $this->repository->upsert(200, 'Danil');
        $this->repository->updateNotifications(200, NotificationType::PromotedIntoGame->bit());

        $this->repository->upsert(200, 'Danil', initialNotifications: 0);

        $this->assertSame(NotificationType::PromotedIntoGame->bit(), $this->repository->findById(200)['notifications']);
    }

    public function testPlainUpsertKeepsExistingNotifications(): void
    {
        $this->repository->upsert(200, 'Danil');
        $this->repository->updateNotifications(200, NotificationType::PromotedIntoGame->bit());

        $this->repository->upsert(200, 'Danil');

        $this->assertSame(NotificationType::PromotedIntoGame->bit(), $this->repository->findById(200)['notifications']);
    }

    public function testUpdateNotificationsOverwritesThePreviousValue(): void
    {
        $this->repository->upsert(200, 'Danil');
        $this->repository->updateNotifications(200, NotificationType::PromotedIntoGame->bit());

        $this->repository->updateNotifications(200, 0);

        $user = $this->repository->findById(200);
        $this->assertSame(0, (int)$user['notifications']);
    }
}