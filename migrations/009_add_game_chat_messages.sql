-- Adds game_chat_messages so a game can also be posted as a normal chat message
-- (chat_id + message_id), alongside the existing game_inline_messages table for
-- inline messages. Two concrete tables keep every row fully populated instead of
-- one table with a nullable inline/chat discriminator.

CREATE TABLE game_chat_messages
(
    game_id    INTEGER   NOT NULL,
    chat_id    BIGINT    NOT NULL,
    message_id BIGINT    NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- PRIMARY KEY leads with game_id, so it indexes the per-game lookup
    -- (findTargetsByGameId) and the ON DELETE CASCADE from games.
    PRIMARY KEY (game_id, chat_id, message_id),
    -- A Telegram message is globally unique by (chat_id, message_id); this also
    -- indexes findGameIdByChatMessage, the per-tap / per-reply reverse lookup.
    UNIQUE (chat_id, message_id),
    FOREIGN KEY (game_id) REFERENCES games (game_id) ON DELETE CASCADE ON UPDATE CASCADE
);
