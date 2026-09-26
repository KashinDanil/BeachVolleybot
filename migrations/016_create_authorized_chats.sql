-- Groups the bot may run games in: added when a root user adds the bot (my_chat_member), removed when it leaves.
CREATE TABLE authorized_chats
(
    chat_id    BIGINT PRIMARY KEY,
    added_by   BIGINT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Grandfather groups the bot already operates in, so their existing games aren't masked on release.
INSERT OR IGNORE INTO authorized_chats (chat_id)
SELECT DISTINCT chat_id FROM pinned_messages;

INSERT OR IGNORE INTO authorized_chats (chat_id)
SELECT DISTINCT chat_id FROM game_messages WHERE chat_id IS NOT NULL;
