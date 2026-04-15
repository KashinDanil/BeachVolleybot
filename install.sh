#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# Paths in paths.env are relative to config/
source ./config/paths.env
LOGS_DIR="${SCRIPT_DIR}/config/${LOGS_DIR}"
QUEUES_DIR="${SCRIPT_DIR}/config/${QUEUES_DIR}"
DB_DATA_DIR="${SCRIPT_DIR}/config/${DB_DATA_DIR}"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

ok()      { echo -e "  ${GREEN}✔${NC} $1"; }
fail()    { echo -e "  ${RED}✘${NC} $1"; exit 1; }
warn()    { echo -e "  ${YELLOW}!${NC} $1"; }
section() { echo -e "\n== $1 =="; }

ensure_directory() {
    mkdir -p "$1" && chmod 755 "$1"
    ok "$2: $1"
}

check_configuration() {
    section "Checking configuration"

    if grep -q "'XXX'" "${SCRIPT_DIR}/config/config.php"; then
        fail "config/config.php still contains 'XXX' placeholders. Configure it first (see README.md)"
    fi
    ok "config/config.php is configured"
}

check_prerequisites() {
    section "Checking prerequisites"

    command -v php      >/dev/null 2>&1 || fail "php is not installed"
    ok "php found"

    command -v composer >/dev/null 2>&1 || fail "composer is not installed"
    ok "composer found"

    local extensions=(curl json pcntl sqlite3 pdo)
    for extension in "${extensions[@]}"; do
        php -r "exit(extension_loaded('${extension}') ? 0 : 1);" || fail "PHP extension '${extension}' is missing"
        ok "ext-${extension}"
    done
}

install_dependencies() {
    section "Installing Composer dependencies"

    composer install
    ok "Dependencies installed"
}

create_runtime_directories() {
    section "Creating runtime directories"

    ensure_directory "$LOGS_DIR" "Logs"
    ensure_directory "$QUEUES_DIR" "Queues"
}

set_bin_permissions() {
    section "Setting executable permissions"

    chmod +x bin/migrate bin/run_worker
    ok "bin/migrate"
    ok "bin/run_worker"
}

run_migrations() {
    section "Running database migrations"

    ensure_directory "$DB_DATA_DIR" "Database"

    php bin/migrate
    ok "Migrations applied"

    local database_file="${DB_DATA_DIR}/${DB_FILENAME}"
    if [ -f "$database_file" ]; then
        chmod 664 "$database_file"
        ok "Database file permissions set"
    fi
}

run_tests() {
    section "Running tests"

    vendor/bin/phpunit --bootstrap tests/config.php --no-progress tests/
    ok "All tests passed"
}

start_queue_worker() {
    section "Starting queue worker"

    make -C "$SCRIPT_DIR" queue-worker-start
    ok "Queue worker started"
}

main() {
    check_configuration
    check_prerequisites
    install_dependencies
    create_runtime_directories
    set_bin_permissions
    run_migrations
    run_tests
    start_queue_worker

    section "${GREEN}Installation complete${NC}"
    echo ""
    echo "There is only one step left: set up the Telegram webhook pointing to public/tg-bot.php (must be HTTPS)."
    echo "If you've already done that, you're all set!"
    echo ""
}

main
