-- Merges game_inline_messages and game_chat_messages into one game_messages table,
-- reversing 009's two-table split. A row is either an inline message (inline_message_id)
-- or a chat message (chat_id + message_id), enforced by CHECK. inline_query_id is the
-- originating inline query id, null for chat rows and for pre-migration inline rows.
-- Both source tables are leaf children of games, so DROP fires no cascade and the
-- 004/006/011 temp-table dance is unnecessary.

CREATE TABLE game_messages
(
    game_id           INTEGER   NOT NULL,
    chat_id           BIGINT,
    message_id        BIGINT,
    inline_message_id VARCHAR,
    inline_query_id   VARCHAR,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (inline_message_id IS NOT NULL OR (chat_id IS NOT NULL AND message_id IS NOT NULL)),
    FOREIGN KEY (game_id) REFERENCES games (game_id) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO game_messages (game_id, inline_message_id, created_at)
SELECT game_id, inline_message_id, created_at
FROM game_inline_messages;

INSERT INTO game_messages (game_id, chat_id, message_id, created_at)
SELECT game_id, chat_id, message_id, created_at
FROM game_chat_messages;

-- inline_message_id is globally unique in Telegram; findGameIdByInlineMessageId assumes it.
CREATE UNIQUE INDEX idx_game_messages_inline_message_id
    ON game_messages (inline_message_id) WHERE inline_message_id IS NOT NULL;

-- Preserves 009's UNIQUE (chat_id, message_id); indexes findGameIdByChatMessage.
CREATE UNIQUE INDEX idx_game_messages_chat_message
    ON game_messages (chat_id, message_id) WHERE chat_id IS NOT NULL AND message_id IS NOT NULL;

-- Each message keys off its own inline query, so the id is unique when set.
CREATE UNIQUE INDEX idx_game_messages_inline_query_id
    ON game_messages (inline_query_id) WHERE inline_query_id IS NOT NULL;

-- Serves findByGameId and the ON DELETE CASCADE (was the leading game_id of both PKs).
CREATE INDEX idx_game_messages_game_id ON game_messages (game_id);

DROP TABLE game_inline_messages;
DROP TABLE game_chat_messages;
