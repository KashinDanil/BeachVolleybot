-- Per-user notification opt-ins packed into one INTEGER bitmask (see NotificationType /
-- NotificationSettings): each type owns a bit, 1 = notify. NULL = never set up, which notifies
-- nothing, the same as 0.
ALTER TABLE users ADD COLUMN notifications INTEGER;
