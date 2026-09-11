-- Per-game switches that no query filters on: one nullable JSON blob, so the next setting
-- costs a key instead of a migration. TEXT, like weather_cache.data_json — SQLite's JSON
-- functions read TEXT fine, and a JSON type keyword would land on NUMERIC affinity.

ALTER TABLE games
    ADD COLUMN settings_json TEXT;
