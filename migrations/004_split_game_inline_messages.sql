-- Splits the single games.inline_message_id column into a junction table so
-- one game can have many inline messages.
--
-- SQLite refuses ALTER TABLE games DROP COLUMN inline_message_id because the
-- column carries a UNIQUE constraint, so we rebuild the table. With foreign
-- keys ON, DROP TABLE games would fire ON DELETE CASCADE on every child row,
-- and PRAGMA foreign_keys cannot be toggled inside a transaction. The
-- workaround is to stash children in temp tables, empty the live tables so
-- the cascade has nothing left to bite, do the rebuild, then restore.

CREATE TEMP TABLE _games_backup AS
SELECT game_id,
       inline_query_id,
       inline_message_id,
       title,
       location,
       created_by,
       created_at,
       updated_at
FROM games;

CREATE TEMP TABLE _game_players_backup AS
SELECT *
FROM game_players;
CREATE TEMP TABLE _game_slots_backup AS
SELECT *
FROM game_slots;

DELETE
FROM game_slots;
DELETE
FROM game_players;

DROP TABLE games;

CREATE TABLE games
(
    game_id         INTEGER PRIMARY KEY AUTOINCREMENT,
    inline_query_id VARCHAR   NOT NULL UNIQUE,
    title           TEXT      NOT NULL,
    location        VARCHAR,
    created_by      BIGINT    NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO games (game_id, inline_query_id, title, location, created_by, created_at, updated_at)
SELECT game_id, inline_query_id, title, location, created_by, created_at, updated_at
FROM _games_backup;

CREATE TABLE game_inline_messages
(
    game_id           INTEGER   NOT NULL,
    inline_message_id VARCHAR   NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (game_id, inline_message_id),
    FOREIGN KEY (game_id) REFERENCES games (game_id) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO game_inline_messages (game_id, inline_message_id)
SELECT game_id, inline_message_id
FROM _games_backup;

INSERT INTO game_players
SELECT *
FROM _game_players_backup;
INSERT INTO game_slots
SELECT *
FROM _game_slots_backup;

DROP TABLE _games_backup;
DROP TABLE _game_players_backup;
DROP TABLE _game_slots_backup;
