#!/usr/bin/env bash

# Exit on error (-e), undefined variable (-u), or pipe failure (-o pipefail)
set -euo pipefail

# Resolve the absolute path to the project root and cd into it
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# Load runtime directory paths from the single source of truth.
# Paths are relative to the config/ directory.
source ./config/paths.env
LOGS_DIR="${SCRIPT_DIR}/config/${LOGS_DIR}"
QUEUES_DIR="${SCRIPT_DIR}/config/${QUEUES_DIR}"
DB_DATA_DIR="${SCRIPT_DIR}/config/${DB_DATA_DIR}"

# ANSI color codes for formatted output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Helper functions for printing colored status messages
ok()   { echo -e "  ${GREEN}✔${NC} $1"; }
fail() { echo -e "  ${RED}✘${NC} $1"; exit 1; }
warn() { echo -e "  ${YELLOW}!${NC} $1"; }

# --- 1. Check that config/config.php has been configured (no 'XXX' placeholders left) ---

echo "== Checking configuration =="

CONFIG_FILE="${SCRIPT_DIR}/config/config.php"

if grep -q "'XXX'" "$CONFIG_FILE"; then
    fail "config/config.php still contains 'XXX' placeholder values. Please configure it before running install.sh (see README.md)"
fi
ok "config/config.php is configured"

# --- 2. Check that php and composer are installed, and required PHP extensions are available ---

echo ""
echo "== Checking prerequisites =="

# Verify php is on PATH
command -v php      >/dev/null 2>&1 || fail "php is not installed"
ok "php found"

# Verify composer is on PATH
command -v composer >/dev/null 2>&1 || fail "composer is not installed"
ok "composer found"

# Check each required PHP extension via `php -r extension_loaded()`
REQUIRED_EXTENSIONS=(curl json pcntl sqlite3 pdo)
for extension in "${REQUIRED_EXTENSIONS[@]}"; do
    php -r "exit(extension_loaded('${extension}') ? 0 : 1);" || fail "PHP extension '${extension}' is missing"
    ok "ext-${extension}"
done

# --- 3. Install PHP dependencies via Composer ---

echo ""
echo "== Installing Composer dependencies =="
composer install
ok "Dependencies installed"

# --- 4. Create runtime directories (logs and queues) sibling to the project root ---

echo ""
echo "== Creating runtime directories =="


# Create logs directory with read/write/execute for owner, read/execute for others
mkdir -p "$LOGS_DIR" && chmod 755 "$LOGS_DIR"
ok "Logs directory: ${LOGS_DIR}"

# Create queues directory with the same permissions
mkdir -p "$QUEUES_DIR" && chmod 755 "$QUEUES_DIR"
ok "Queues directory: ${QUEUES_DIR}"

# --- 5. Make bin scripts executable ---

echo ""
echo "== Setting executable permissions on bin scripts =="

# Grant execute permission to CLI entry points
chmod +x bin/migrate bin/run_worker
ok "bin/migrate"
ok "bin/run_worker"

# --- 6. Create database directory and apply SQL migrations ---

echo ""
echo "== Running database migrations =="

# Ensure the SQLite data directory exists with proper permissions
mkdir -p "$DB_DATA_DIR" && chmod 755 "$DB_DATA_DIR"

# Run the migration script to create/update the SQLite database
php bin/migrate
ok "Migrations applied"

# Make the SQLite file read/writable by owner and group, readable by others
DB_FILE="${DB_DATA_DIR}/${DB_FILENAME}"
if [ -f "$DB_FILE" ]; then
    chmod 664 "$DB_FILE"
    ok "Database file permissions set"
fi

# --- 7. Run PHPUnit tests to verify the installation ---

echo ""
echo "== Running tests =="

vendor/bin/phpunit --bootstrap tests/config.php tests/
ok "All tests passed"

# --- 8. Start the queue worker in background ---

echo ""
echo "== Starting queue worker =="

make -C "$SCRIPT_DIR" queue-worker-start
ok "Queue worker started"

# --- 9. Print next steps for the user ---

echo ""
echo -e "${GREEN}== Installation complete ==${NC}"
echo ""
echo "There is only one step left: set up the Telegram webhook pointing to public/tg-bot.php (must be HTTPS)."
echo "If you've already done that, you're all set!"
echo ""
