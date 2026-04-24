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
- **Weather forecasts** — hourly forecast for the game window, attached to the message and refreshable on demand; powered by Open-Meteo, resolved per known venue, cached in SQLite, and computed off the request path by a dedicated worker
- **Multi-language support** — English (default), Russian, Spanish
- **Concurrency handling** via file-based locking
- **Asynchronous processing** via file-based queues and workers
- **Rate limiting** — respects Telegram API rate limits via `RateLimitedBotApi`
- **Message pinning** — game messages are automatically pinned if the bot has permissions; past-date games are auto-unpinned when the next game is pinned, and the bot's own pin service notifications are cleaned up automatically
- **Admin panel** — manage games, players, equipment, and view logs via Telegram callback interface
- **Game add-ons** — pipeline of post-processing add-ons (merge consecutive slots, stylize title, weather)

## Architecture

The architecture is designed with **future scalability in mind**. While the current implementation avoids external infrastructure, it can be **easily migrated to a traditional stack** if needed.

### Request Flow

```
Telegram Webhook
  → public/tg-bot.php (validation, authentication)
    → IncomingMessageRouter (routing)
      → IncomingMessageQueueRouter (enqueue into per-chat/per-game/pin queue)
        → AppQueueWorker (async processing)
          → AppQueueProcessor (dispatch)
            → UpdateProcessors (game logic)
              ├→ RateLimitedBotApi (rate-limited Telegram API calls)
              └→ WeatherEnqueuer (when a game needs a forecast)
                 → weather queue
                   → WeatherQueueWorker
                     → WeatherQueueProcessor
                       → OpenMeteoWeatherClient (forecast fetch + cache)
                         → InlineMessageRefresher (re-render the game message with weather)
```

### Project Structure

```
├── bin/                 # CLI scripts (migrate, run_worker)
├── config/              # Configuration constants and paths.env
├── migrations/          # SQL migrations (games, pinned messages, weather cache)
├── localization/        # Translation files (ru.json, es.json)
├── public/              # Webhook entry point (tg-bot.php)
├── src/
│   ├── Common/          # Logger, extractors, input strategies, date/time resolvers, update-id tracker
│   ├── Database/        # Connection, repositories, migrator
│   ├── Errors/          # Error types
│   ├── Game/            # Core game logic, models, add-ons (including WeatherAddOn)
│   ├── Localization/    # Translator
│   ├── Log/             # Log file management
│   ├── Processors/      # Queue processors and update/admin/callback processors
│   ├── Routing/         # Message routing and queue selection
│   ├── Telegram/        # Message sender, builders, MarkdownV2, inline refresher, rate-limited API
│   ├── Validator/       # Request validation rules
│   ├── Weather/         # Open-Meteo client, forecast cache, known venues, weather queue payloads
│   └── Workers/         # AppQueueWorker, WeatherQueueWorker
└── tests/               # PHPUnit tests
```

## Setup

#### 1. Configure `config/config.php` and `config/paths.env`

**config/config.php:**

- #### `VERBOSE_LOGGING` — enable/disable verbose logging (`true`/`false`).

- #### `ADMINS_TELEGRAM_USER_IDS` — an array of Telegram user IDs that should have admin access to the bot. Can be left empty (`[]`).

- #### `BOT_USERNAME` — the username of your Telegram bot (without the `@` prefix), as set in **BotFather**.

- #### `TG_BOT_ACCESS_TOKEN` — the HTTP API token provided by **Telegram BotFather** after creating your bot.

- #### `APP_TOKEN_HASH` — a hash of your webhook secret token.

  Generate it with the following command:

  ```bash
  php -r 'echo password_hash("YOUR_SECRET_TOKEN", PASSWORD_DEFAULT), PHP_EOL;'
  ```

  Use the same `YOUR_SECRET_TOKEN` as the `secret_token` when calling [setWebhook](https://core.telegram.org/bots/api#setwebhook).

- #### `GAME_ADD_ONS` — ordered list of add-on classes applied to each game after processing.

- #### `TG_MAX_REQUESTS_PER_SECOND` — the maximum number of Telegram API requests per second (default: `19`).

**config/paths.env:**

- #### `LOGS_DIR` — path to the logs directory (relative to `config/`).

- #### `QUEUES_DIR` — path to the queues directory (relative to `config/`).

- #### `DB_DATA_DIR` — path to the database directory (relative to `config/`).

- #### `DB_FILENAME` — SQLite database filename.

#### 2. Run the installation script

Run the script as the same user that will execute PHP requests (e.g. `www-data`):

```bash
bash install.sh
```

This checks prerequisites, installs dependencies, creates runtime directories, applies migrations, runs all tests, and starts both workers.

#### 3. Set up the webhook

Point Telegram to `public/tg-bot.php` on your server. The endpoint must be accessible over HTTPS.

## Workers

The project runs two workers concurrently: the **app worker** (processes Telegram updates from the main queue) and the **weather worker** (fetches forecasts for games). Both are started automatically by `install.sh`.

To start both in the background:

```bash
make workers-start
```

App errors log to `logs/app-worker-errors.log`; weather errors log to `logs/weather-worker-errors.log`.

To restart both (stops running processes, then starts fresh ones):

```bash
make workers-restart
```

To stop both:

```bash
make workers-stop
```

To run a single worker in the foreground (stdout output, useful during development):

```bash
make app-worker-run
make weather-worker-run
```

## Testing

```bash
vendor/bin/phpunit --bootstrap tests/config.php
```
