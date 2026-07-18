-- Standardize identity vocabulary on "user" WITHOUT losing any records.
-- Follows the 004/005 idiom: with foreign_keys ON we cannot DROP a parent
-- table while children hold rows (ON DELETE CASCADE would fire), and
-- PRAGMA foreign_keys cannot be toggled inside the migrator's transaction.
-- So: back up all affected tables, empty the live tables so the cascade has
-- nothing to bite, rebuild under new names, then restore every row.

-- 1. Stash all three affected tables (SELECT * preserves all columns + rows).
CREATE TEMP TABLE _players_backup      AS SELECT * FROM players;
CREATE TEMP TABLE _game_players_backup AS SELECT * FROM game_players;
CREATE TEMP TABLE _game_slots_backup   AS SELECT * FROM game_slots;

-- 2. Empty live tables (deepest child first) so DROP fires no cascade.
DELETE FROM game_slots;
DELETE FROM game_players;
DELETE FROM players;

DROP TABLE game_slots;
DROP TABLE game_players;
DROP TABLE players;

-- 3. Recreate with new names, column definitions identical to the current
--    live schema (players from 001; game_players NOT NULL time from 005;
--    game_slots from 001) -- only the table names and FK targets change.
CREATE TABLE users (
    telegram_user_id BIGINT PRIMARY KEY,
    first_name VARCHAR NOT NULL,
    last_name VARCHAR,
    username VARCHAR,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE game_users (
    game_id INTEGER NOT NULL,
    telegram_user_id BIGINT NOT NULL,
    time VARCHAR NOT NULL,
    volleyball INTEGER NOT NULL DEFAULT 0,
    net INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games (game_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (telegram_user_id) REFERENCES users (telegram_user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (game_id, telegram_user_id)
);

CREATE TABLE game_slots (
    game_id INTEGER NOT NULL,
    telegram_user_id BIGINT NOT NULL,
    position BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id, telegram_user_id) REFERENCES game_users (game_id, telegram_user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (game_id, position)
);

-- 4. Restore every row. Explicit column lists guard against any column-order
--    drift between the backup and the new tables.
INSERT INTO users (telegram_user_id, first_name, last_name, username, created_at, updated_at)
SELECT telegram_user_id, first_name, last_name, username, created_at, updated_at
FROM _players_backup;

INSERT INTO game_users (game_id, telegram_user_id, time, volleyball, net, created_at, updated_at)
SELECT game_id, telegram_user_id, time, volleyball, net, created_at, updated_at
FROM _game_players_backup;

INSERT INTO game_slots (game_id, telegram_user_id, position, created_at)
SELECT game_id, telegram_user_id, position, created_at
FROM _game_slots_backup;

DROP TABLE _players_backup;
DROP TABLE _game_players_backup;
DROP TABLE _game_slots_backup;
