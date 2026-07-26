-- Renames games.inline_query_id to the message-source-agnostic game_key.
--
-- A game can now originate from an inline query (tap-the-article) or from a
-- plain chat message that @mentions the bot, so the per-game identity token is
-- no longer specific to inline queries. SQLite >= 3.25 renames the column in
-- place, preserving its UNIQUE NOT NULL constraint and existing values.

ALTER TABLE games
    RENAME COLUMN inline_query_id TO game_key;
