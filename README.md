> **Warning:** This project is wildly and unnecessarily overcomplicated for what it does. Proceed with humility.

## Overview

This project was created to address a common frustration: _manually copying participant lists, adding names, and reposting them in group chats_. This workflow is inconvenient and error-prone, especially when **multiple people attempt to join simultaneously**, which can lead to **concurrency issues**.

## Solution

**BeachVolleyBot** simplifies this process by allowing participants to **join a game with a single button click**, eliminating the need for manual list management in group chats.

## Features

- **Game creation** via Telegram inline queries
- **Join / Leave** with a single button click
- **Equipment tracking** — volleyballs and nets per player
- **Time extraction** from game titles (e.g. "Beach Volleyball 18:00")
- **Location setting** with Google Maps link
- **Multi-language support** — English (default), Russian, Spanish
- **Concurrency handling** via file-based locking
- **Asynchronous processing** via file-based queue and worker
- **Rate limiting** — respects Telegram API rate limits via `RateLimitedBotApi`

## Architecture

The architecture is designed with **future scalability in mind**. While the current implementation avoids external infrastructure, it can be **easily migrated to a traditional stack** if needed.

### Request Flow

```
Telegram Webhook
  → public/tg-bot.php (validation, authentication)
    → IncomingMessageRouter (routing)
      → IncomingMessageQueueRouter (enqueue)
        → FileQueueWorker (async processing)
          → AppQueueProcessor (dispatch)
            → UpdateProcessors (game logic)
              → RateLimitedBotApi (rate-limited Telegram API calls)
```

### Project Structure

```
├── bin/                 # CLI scripts (migrate, run_worker)
├── config/              # Configuration constants
├── migrations/          # SQL migrations
├── localization/        # Translation files (ru.json, es.json)
├── public/              # Webhook entry point (tg-bot.php)
├── src/
│   ├── Common/          # Logger, extractors, input strategies
│   ├── Database/        # Connection, repositories, migrator
│   ├── Errors/          # Error types
│   ├── Game/            # Core game logic, models, add-ons
│   ├── Localization/    # Translator
│   ├── Processors/      # Update processors (create, join, leave, equipment)
│   ├── Routing/         # Message routing
│   ├── Telegram/        # Message sender, builders, MarkdownV2
│   ├── Validator/       # Request validation rules
│   └── Workers/         # File queue worker
└── tests/               # PHPUnit tests
```

## Setup

### Automated Setup

Run the install script to check prerequisites, install dependencies, apply migrations, and verify the installation:

```bash
bash install.sh
```

### Manual Setup

Follow these steps to configure the project manually.

#### Prerequisites

- PHP with extensions: `curl`, `json`, `pcntl`, `sqlite3`, `pdo`
- Composer

#### 1. Install dependencies

Run Composer to install the required PHP dependencies:

```bash
composer install
```

#### 2. Run database migrations

```bash
php bin/migrate
```

This creates the SQLite database at `../db/data/beach_volleybot.sqlite` and applies all pending migrations.

#### 3. Update configuration constants

Open the following file:

```php
config/config.php
```

Replace the constants with your actual values.

- #### `TG_BOT_ACCESS_TOKEN` — provided by **Telegram BotFather** after creating your bot.

- #### `APP_TOKEN_HASH` — a hash of your webhook secret token.

  Generate it with the following command:

  ```bash
  php -r 'echo password_hash("TELEGRAM_BOT_API_SECRET_TOKEN", PASSWORD_DEFAULT), PHP_EOL;'
  ```

  Replace `TELEGRAM_BOT_API_SECRET_TOKEN` in the command with a `secret_token` that you [configure Telegram to send](https://core.telegram.org/bots/api#setwebhook) in the `X-Telegram-Bot-Api-Secret-Token` header to your server as an extra safety measure.

- #### `BASE_LOG_DIR` — the absolute path to the directory where log files will be stored.

- #### `TG_MAX_REQUESTS_PER_SECOND` — the maximum number of Telegram API requests per second (default: `20`).

#### 4. Set up the webhook

Point Telegram to `public/tg-bot.php` on your server. The endpoint must be accessible over HTTPS.

#### 5. Start the queue worker

```bash
make qw
```

This runs the worker in the background with error logging to `../logs/queue-worker-errors.log`.

To run synchronously (foreground, stdout output):

```bash
make qws
```

To restart the worker (kills the existing process and starts a new one):

```bash
make qwr
```

## Testing

```bash
vendor/bin/phpunit --bootstrap tests/config.php
```
