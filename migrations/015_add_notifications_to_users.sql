-- Per-user notification opt-ins packed into one INTEGER bitmask (see NotificationType /
-- NotificationSettings): each type owns a bit, 1 = notify. ADD COLUMN with a constant DEFAULT
-- needs no table rebuild (cf. 007); existing rows start at 0, every type off.
ALTER TABLE users ADD COLUMN notifications INTEGER NOT NULL DEFAULT 0;
