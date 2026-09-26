-- Per-message authorization memo: NULL = not checked yet, 1 = checked and authorized, 0 = rejected (message masked).
ALTER TABLE game_messages ADD COLUMN authorized INTEGER;

-- Grandfather every message that predates this feature so existing games are never masked on
-- release. This is the complete grandfather: the allowlist backfill (016) can only recover groups
-- that left a chat_id behind (pins / chat messages), but inline-only groups store no chat_id
-- anywhere. Marking existing rows authorized covers them all; only new games get checked.
UPDATE game_messages SET authorized = 1 WHERE authorized IS NULL;
