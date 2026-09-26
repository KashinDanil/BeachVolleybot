<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

readonly class ChatMember
{
    public function __construct(
        public TelegramUser $user,
        public ?ChatMemberStatus $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            user: TelegramUser::fromArray($data['user']),
            status: isset($data['status']) ? ChatMemberStatus::tryFrom($data['status']) : null,
        );
    }

    public function isPresent(): bool
    {
        return $this->status?->isPresent() ?? false;
    }

    public function hasLeft(): bool
    {
        return $this->status?->hasLeft() ?? false;
    }
}
