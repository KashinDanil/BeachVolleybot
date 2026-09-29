-- 2-letter prefix of the user's last Telegram language_code, 'en' when it has none; NULL = never sent.
ALTER TABLE users ADD COLUMN language_code VARCHAR;
