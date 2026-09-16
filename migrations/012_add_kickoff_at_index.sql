-- Both the scan and every weather job filter games by kickoff_at.
CREATE INDEX IF NOT EXISTS idx_games_kickoff_at ON games (kickoff_at);
