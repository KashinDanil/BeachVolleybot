> **Warning:** This project is wildly and unnecessarily overcomplicated for what it does. Proceed with humility.

## Overview

This project was created to address a common frustration: _manually copying participant lists, adding names, and reposting them in group chats_. This workflow is inconvenient and error-prone, especially when **multiple people attempt to join simultaneously**, which can lead to **concurrency issues**.

## Solution

**BeachVolleyBot** simplifies this process by allowing participants to **join a game with a single button click**, eliminating the need for manual list management in group chats.

## Architecture

The architecture is designed with **future scalability in mind**. While the current implementation avoids external infrastructure, it can be **easily migrated to a traditional stack** if needed.

## Setup

Follow these steps to configure the project locally.

### 1. Install dependencies

Run Composer to install the required PHP dependencies:

```bash
composer install
```

### 2. Update configuration constants

Open the following file:

```php
src/Config/config.php
```

Replace the constants with your actual values.

- #### `TG_BOT_ACCESS_TOKEN` - This value is provided by **Telegram BotFather** after creating your bot.

- #### `APP_TOKEN_HASH` - This value should contain a hash of your webhook secret token.
- #### `BASE_LOG_DIR` - The absolute path to the directory where log files will be stored.

Generate it with the following command:

```bash
php -r 'echo password_hash("TELEGRAM_BOT_API_SECRET_TOKEN", PASSWORD_DEFAULT), PHP_EOL;'
```

Replace `TELEGRAM_BOT_API_SECRET_TOKEN` in the command with a `secret_token` that you [configure Telegram to send](https://core.telegram.org/bots/api#setwebhook) in the `X-Telegram-Bot-Api-Secret-Token` header to your server as an extra safety measure.
