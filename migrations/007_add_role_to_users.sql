-- Every user has a role, stored ascending by privilege: 0 = player (default),
-- 1 = admin, 2 = root. SQLite supports ADD COLUMN with a constant DEFAULT, so no
-- table rebuild is needed (unlike 005/006); existing rows become players.
ALTER TABLE users ADD COLUMN role INTEGER NOT NULL DEFAULT 0;
