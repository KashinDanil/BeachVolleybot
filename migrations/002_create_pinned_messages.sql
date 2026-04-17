CREATE TABLE IF NOT EXISTS pinned_messages
(
    chat_id      BIGINT    NOT NULL,
    message_id   BIGINT    NOT NULL,
    message_json TEXT      NOT NULL,
    unpin_after  TIMESTAMP,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (chat_id, message_id)
);