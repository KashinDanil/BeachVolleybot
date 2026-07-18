-- Every user has a role: 0 = root, 1 = player (default), 2 = admin.
-- SQLite supports ADD COLUMN with a constant DEFAULT, so no table rebuild is
-- needed (unlike 005/006); existing rows become players.
ALTER TABLE users ADD COLUMN role INTEGER NOT NULL DEFAULT 1;
