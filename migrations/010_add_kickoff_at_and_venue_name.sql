-- The structured facts the free-text title already carries, resolved once at write
-- time instead of re-parsed on every read. Nullable while existing rows are still
-- unfilled; a later migration backfills them and makes kickoff_at NOT NULL.

ALTER TABLE games
    ADD COLUMN kickoff_at TIMESTAMP;

ALTER TABLE games
    ADD COLUMN venue_name VARCHAR;
