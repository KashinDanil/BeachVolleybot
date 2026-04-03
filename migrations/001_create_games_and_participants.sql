CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inline_message_id TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

CREATE TABLE IF NOT EXISTS participants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL,
    telegram_id INTEGER NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT,
    username TEXT,
    ball INTEGER NOT NULL DEFAULT 0,
    net INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (game_id) REFERENCES games (id),
    UNIQUE (game_id, telegram_id)
);

CREATE TABLE IF NOT EXISTS participant_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL,
    participant_id INTEGER NOT NULL,
    position INTEGER NOT NULL,
    plus_one_number INTEGER NOT NULL DEFAULT 0,
FOREIGN KEY (game_id) REFERENCES games (id),
    FOREIGN KEY (participant_id) REFERENCES participants (id)
);
