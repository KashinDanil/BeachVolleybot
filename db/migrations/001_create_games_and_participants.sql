CREATE TABLE IF NOT EXISTS games (
    -- SQLite's INTEGER is already 64-bit; AUTOINCREMENT requires this exact keyword
    game_id INTEGER PRIMARY KEY AUTOINCREMENT,
    inline_query_id VARCHAR NOT NULL UNIQUE,
    inline_message_id VARCHAR NOT NULL UNIQUE,
    title TEXT NOT NULL,
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS players (
    telegram_user_id BIGINT PRIMARY KEY,
    first_name VARCHAR NOT NULL,
    last_name VARCHAR,
    username VARCHAR,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS game_players (
    game_id INTEGER NOT NULL,
    telegram_user_id BIGINT NOT NULL,
    time VARCHAR,
    ball INTEGER NOT NULL DEFAULT 0,
    net INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- The project is small enough to afford foreign keys and cascade behavior
    FOREIGN KEY (game_id) REFERENCES games (game_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (telegram_user_id) REFERENCES players (telegram_user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (game_id, telegram_user_id)
);

CREATE TABLE IF NOT EXISTS game_slots (
    game_id INTEGER NOT NULL,
    telegram_user_id BIGINT NOT NULL,
    position BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- The project is small enough to afford foreign keys and cascade behavior
    FOREIGN KEY (game_id, telegram_user_id) REFERENCES game_players (game_id, telegram_user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (game_id, position)
);

/*
  Table connections:

  +---------------+       +------------------------+       +------------------------+
  |  games        |       |     game_players       |       |        players         |
  +---------------+       +------------------------+       +------------------------+
  | game_id (PK)  |<------| game_id (PK)           |       |                        |
  |               |       | telegram_user_id (PK)  |------>| telegram_user_id (PK)  |
  +---------------+       +------------------------+       +------------------------+
                                     |
                                     |
                                     v
                          +------------------------+
                          |      game_slots        |
                          +------------------------+
                          | game_id (PK) (FK)      |
                          | telegram_user_id (FK)  |
                          | position (PK)          |
                          +------------------------+

  players 1---* game_players *---1 games
  game_players 1---* game_slots
*/