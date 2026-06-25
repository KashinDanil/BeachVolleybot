-- Player arrival time is a product invariant.
-- SQLite cannot ALTER an existing column to add NOT NULL, so rebuild the table.
-- The INSERT fails if any existing row violates the invariant.

CREATE TEMP TABLE _game_players_backup AS
SELECT *
FROM game_players;

CREATE TEMP TABLE _game_slots_backup AS
SELECT *
FROM game_slots;

DELETE
FROM game_slots;

DROP TABLE game_players;

CREATE TABLE game_players (
    game_id INTEGER NOT NULL,
    telegram_user_id BIGINT NOT NULL,
    time VARCHAR NOT NULL,
    volleyball INTEGER NOT NULL DEFAULT 0,
    net INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games (game_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (telegram_user_id) REFERENCES players (telegram_user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (game_id, telegram_user_id)
);

INSERT INTO game_players (game_id, telegram_user_id, time, volleyball, net, created_at, updated_at)
SELECT game_id, telegram_user_id, time, volleyball, net, created_at, updated_at
FROM _game_players_backup;

INSERT INTO game_slots (game_id, telegram_user_id, position, created_at)
SELECT game_id, telegram_user_id, position, created_at
FROM _game_slots_backup;

DROP TABLE _game_players_backup;
DROP TABLE _game_slots_backup;
