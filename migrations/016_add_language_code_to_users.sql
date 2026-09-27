-- Raw Telegram language_code of the user's last seen update; NULL = unknown, the bot falls back to English.
ALTER TABLE users ADD COLUMN language_code VARCHAR;
