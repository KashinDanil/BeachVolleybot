<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

readonly class TelegramChatMemberUpdated
{
    public function __construct(
        public TelegramChat $chat,
        public TelegramUser $from,
        public ChatMember $oldChatMember,
        public ChatMember $newChatMember,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            chat: TelegramChat::fromArray($data['chat']),
            from: TelegramUser::fromArray($data['from']),
            oldChatMember: ChatMember::fromArray($data['old_chat_member']),
            newChatMember: ChatMember::fromArray($data['new_chat_member']),
        );
    }

    public function botPresent(): bool
    {
        return $this->newChatMember->isPresent();
    }

    public function botLeft(): bool
    {
        return $this->newChatMember->hasLeft();
    }
}
