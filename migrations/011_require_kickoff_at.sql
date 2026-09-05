-- Every game carries a kickoff: all three creation paths reject a title without a
-- time, so make the column say so.
--
-- SQLite cannot ALTER an existing column to add NOT NULL, so rebuild the table using
-- the recipe from 004: foreign keys cannot be toggled inside a transaction, so stash
-- the children, empty the live tables to leave the cascade nothing to bite, rebuild,
-- then restore. The INSERT fails if any row still has no kickoff.

CREATE TEMP TABLE _games_backup AS
SELECT *
FROM games;

CREATE TEMP TABLE _game_inline_messages_backup AS
SELECT *
FROM game_inline_messages;
CREATE TEMP TABLE _game_chat_messages_backup AS
SELECT *
FROM game_chat_messages;
CREATE TEMP TABLE _game_users_backup AS
SELECT *
FROM game_users;
CREATE TEMP TABLE _game_slots_backup AS
SELECT *
FROM game_slots;

DELETE
FROM game_slots;
DELETE
FROM game_users;
DELETE
FROM game_inline_messages;
DELETE
FROM game_chat_messages;

DROP TABLE games;

CREATE TABLE games
(
    game_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    game_key   VARCHAR   NOT NULL UNIQUE,
    title      TEXT      NOT NULL,
    kickoff_at TIMESTAMP NOT NULL,
    venue_name VARCHAR,
    location   VARCHAR,
    created_by BIGINT    NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO games (game_id, game_key, title, kickoff_at, venue_name, location, created_by, created_at, updated_at)
SELECT game_id, game_key, title, kickoff_at, venue_name, location, created_by, created_at, updated_at
FROM _games_backup;

INSERT INTO game_inline_messages
SELECT *
FROM _game_inline_messages_backup;
INSERT INTO game_chat_messages
SELECT *
FROM _game_chat_messages_backup;
INSERT INTO game_users
SELECT *
FROM _game_users_backup;
INSERT INTO game_slots
SELECT *
FROM _game_slots_backup;

DROP TABLE _games_backup;
DROP TABLE _game_inline_messages_backup;
DROP TABLE _game_chat_messages_backup;
DROP TABLE _game_users_backup;
DROP TABLE _game_slots_backup;
