<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\AuthorizedChatRepository;

final class AuthorizedChatRepositoryTest extends DatabaseTestCase
{
    private AuthorizedChatRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AuthorizedChatRepository($this->db);
    }

    public function testAuthorizeMakesChatAuthorized(): void
    {
        $this->repository->authorize(-100, 42);

        $this->assertTrue($this->repository->isAuthorized(-100));
    }

    public function testUnknownChatIsNotAuthorized(): void
    {
        $this->assertFalse($this->repository->isAuthorized(-100));
    }

    public function testDeauthorizeRemovesChat(): void
    {
        $this->repository->authorize(-100, 42);
        $this->repository->deauthorize(-100);

        $this->assertFalse($this->repository->isAuthorized(-100));
    }

    public function testAuthorizeTwiceKeepsTheLatestAdder(): void
    {
        $this->repository->authorize(-100, 42);
        $this->repository->authorize(-100, 99);

        $this->assertTrue($this->repository->isAuthorized(-100));
        $this->assertSame(99, (int)$this->db->get('authorized_chats', 'added_by', ['chat_id' => -100]));
    }
}
