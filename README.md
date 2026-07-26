> **Warning:** This project is wildly and unnecessarily overcomplicated for what it does. Proceed with humility.

## Overview

This project was created to address a common frustration: _manually copying participant lists, adding names, and reposting them in group chats_. This workflow is inconvenient and error-prone, especially when **multiple people attempt to join simultaneously**, which can lead to **concurrency issues**.

## Solution

**BeachVolleyBot** simplifies this process by allowing participants to **join a game with a single button click**, eliminating the need for manual list management in group chats.

## Features

- **Game creation** via Telegram inline queries, or by sending a message (in a group or DM) that @mentions the bot with the game details (the bot creates the game, deletes the original message, and posts the game itself — deletion needs the bot's delete-messages admin right)
- **Join / Leave** with a single button click
- **Equipment tracking** — volleyballs and nets per user
- **Time extraction** from game titles (e.g. "Beach Volleyball 18:00"); plain time replies (e.g. `19:30`) join the game at that slot
- **Location setting** with Google Maps link, including live-location updates
- **Game sharing** — one game can be reposted into multiple chats. Creating a game in a private DM yields a `Share` button to forward it to a chat; the same game keeps its participant list synchronized across every chat it appears in
- **Title editing** — the game creator can rename a game by replying to its message; in the bot's DM, admins can rename any game the same way (kickoff day must remain in the future)
- **My games** — the `/games` command in DM lists the games a user has created, with pagination and a per-game detail view from which the game can be shared again
- **Welcome flow** — the `/start` command (also triggered by `/help`) shows a welcome message
- **Group help** — `/help` in a group sends the same help text as an ephemeral message, visible only to the person who asked; answered inside the webhook request, since Telegram expires an ephemeral reply 15 seconds after the command
- **Weather forecasts** — hourly forecast for the game window, attached to the message and refreshable on demand; powered by Open-Meteo, resolved per known venue, cached in SQLite, and computed off the request path by a dedicated worker
- **Multi-language support** — English (default), Russian, Spanish
- **Concurrency handling** via file-based locking
- **Asynchronous processing** via file-based queues and workers
- **Rate limiting** — respects Telegram API rate limits via `RateLimitedBotApi`
- **Message pinning** — game messages are automatically pinned if the bot has permissions; past-date games are auto-unpinned when the next game is pinned, and the bot's own pin service notifications are cleaned up automatically
- **Past-game safeguards** — once a game's kickoff has passed, weather refresh is disabled, sharing is blocked, and inline keyboards are stripped on interaction so old messages don't accept further actions
- **Admin panel** — manage games, users, equipment, and view logs via Telegram callback interface
- **Game add-ons** — pipeline of post-processing add-ons (merge consecutive slots, stylize title, weather)

## Architecture

The architecture is designed with **future scalability in mind**. While the current implementation avoids external infrastructure, it can be **easily migrated to a traditional stack** if needed.

### Request Flow

```
Telegram Webhook
  → public/tg-bot.php (validation, authentication)
    → IncomingMessageRouter
        ├→ immediate registry → ProcessorRegistry.resolveProcessor(update)
        │     ├→ InlineQueryProcessor          (inline query)
        │     ├→ CreateGameProcessor           (chosen inline result)
        │     ├→ ForwardGameProcessor          (chosen inline result)
        │     └→ GroupHelpCommandProcessor     (ephemeral commands in a group)
        └→ IncomingMessageQueueRouter → ProcessorRegistry.resolveQueueName(update)
              ├→ game_<id>       queue   (per-game serialization)
              ├→ game_new_<chat> queue   (create-from-message @mention: per-chat serialization)
              ├→ dm_<user>       queue   (per-user DM serialization)
              └→ pin_<chat>      queue   (per-chat pinning serialization)
                  → AppQueueWorker
                    → AppQueueProcessor → ProcessorRegistry.resolveProcessor(update)
                      → UpdateProcessors / UserProcessors / AdminProcessors
                          ├→ RateLimitedBotApi (rate-limited Telegram API calls)
                          └→ WeatherEnqueuer (when a game needs a forecast)
                             → weather queue
                               → WeatherQueueWorker
                                 → WeatherQueueProcessor
                                   → OpenMeteoWeatherClient (forecast fetch + cache)
                                     → GameMessageRefresher (re-render every posted message of the game — inline or chat)
```

Routing is a `ProcessorRegistry` over handlers declaring `matches(update)` and `createProcessor(sender, update)`, returning the first handler that matches. `ProcessorRegistryFactory` owns two lists: the **immediate** one runs inside the webhook request and is consulted first (inline queries, and the ephemeral group `/help`, whose reply Telegram rejects after 15 seconds); the **queued** one adds `routeToQueue(update)` via `AbstractQueuedProcessorHandler` and is consulted again at worker dispatch, which is why `matches()` must stay pure. Match patterns must be mutually exclusive across both lists, enforced by `HandlerExclusivityTest`.

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
│   ├── Game/            # Core game logic, models, add-ons (registry + WeatherAddOn, MergeConsecutiveSlotsAddOn, StylizeTitleAddOn)
│   ├── Localization/    # Translator
│   ├── Log/             # Log file management
│   ├── Processors/
│   │   ├── AdminProcessors/    # Admin panel callbacks (game / user / equipment / logs / settings)
│   │   ├── UserProcessors/     # /help (also /start), /games command and pagination/detail callbacks
│   │   ├── UpdateProcessors/   # Game lifecycle: create, forward, join-with-time, change-title, set-location, pin-message, group help…
│   │   │   └── CallbackQuery/  # Per-game callbacks (join, leave, add/remove volleyball/net, refresh weather)
│   │   ├── Handlers/           # Per-update handlers (matches / createProcessor [/ routeToQueue])
│   │   │   ├── GameHandlers/      # Routed onto game_<id> queue
│   │   │   ├── GroupHandlers/     # Answered on the request — ephemeral group /help
│   │   │   ├── InlineHandlers/    # Answered on the request — inline queries, chosen results
│   │   │   ├── PinHandlers/       # Routed onto pin_<chat> queue
│   │   │   ├── PrivateHandlers/   # Routed onto dm_<user> queue
│   │   │   └── Traits/            # CallbackProcessorResolverTrait — shared callback dispatch
│   │   ├── AbstractProcessorHandler.php
│   │   ├── ProcessorRegistry.php
│   │   ├── ProcessorRegistryFactory.php
│   │   ├── AppQueueProcessor.php
│   │   └── WeatherQueueProcessor.php
│   ├── Routing/         # IncomingMessageRouter + IncomingMessageQueueRouter (delegate to ProcessorRegistry)
│   ├── Telegram/        # Sender, MarkdownV2, rate-limited API, game message refresher
│   │   ├── CallbackData/       # CallbackData / AdminCallbackData / UserCallbackData (+ pageable interface)
│   │   ├── MessageBuilders/    # Game / list / detail / settings / share / help / log builders + factories, keyboards, warnings
│   │   └── Messages/           # Incoming and Outgoing Telegram message types
│   ├── Validator/       # Validator + rules (auth, date/time-in-title, kickoff-in-future, post request, secret token, …)
│   ├── Weather/         # Open-Meteo client, forecast cache, known venues, weather queue payloads
│   │   ├── Forecast/           # Cache, Client, GameWeatherLookup, Models, Formatter, WindowResolver
│   │   ├── Location/           # GameLocationResolver, KnownVenues catalog, VenueDirectory, Venue / VenueAlias
│   │   └── Queue/              # WeatherEnqueuer, WeatherQueuePayload
│   └── Workers/         # AppQueueWorker, WeatherQueueWorker
└── tests/               # PHPUnit tests (Unit + Integration)
```

## Setup

#### 1. Configure `config/config.php` and `config/paths.env`

**config/config.php:**

- #### `VERBOSE_LOGGING` — enable/disable verbose logging (`true`/`false`).

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

#### 4. Register the bot commands

Register the commands through BotFather or `setMyCommands`:

- `/help` and `/new_game` — for the **DM scope**, and for the **group scope with `is_ephemeral: true`**. Without the flag, clients send them as ordinary visible messages in groups, which the bot deliberately ignores — so group help and the group wizard silently never appear.
- `/games` — for the **DM scope only**. It has no group handler, so registering it for groups only advertises a command that does nothing there.

#### 5. Grant admin access

Admin access is stored per user in the `role` column of the `users` table, ordered ascending by privilege: `0` = player (default), `1` = admin, `2` = root. Admin actions require the **admin** or **root** role; root-only actions (e.g. logs) require **root**.

A user row is created the first time someone interacts with the bot. Once it exists, promote them with:

```bash
# admin
sqlite3 <db> "UPDATE users SET role = 1 WHERE telegram_user_id = <telegram_user_id>;"
# root
sqlite3 <db> "UPDATE users SET role = 2 WHERE telegram_user_id = <telegram_user_id>;"
```

## Workers

The project runs two workers concurrently: the **app worker** (processes Telegram updates from per-game / per-DM / per-chat queues) and the **weather worker** (fetches forecasts for games). Both are started automatically by `install.sh`.

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
