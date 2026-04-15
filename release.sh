#!/usr/bin/env bash

# ANSI colors.
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Exit on:
# - any command error (-e)
# - use of unset variable (-u)
# - failure inside pipes (pipefail)
# Also keep ERR traps working in functions/subshells (-E).
set -Eeuo pipefail

# Absolute path to the directory where this script lives.
# The script is expected to live in:
# /var/www/beachvolleybot-production/public
BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Deployment user that should own and run app-level actions.
DEPLOY_USER="beachvolleybot-production"

# Git repository to deploy from.
REPO_URL="https://github.com/KashinDanil/BeachVolleybot"

# Nginx config that contains the /tg-bot location with bundle path.
NGINX_CONF="/etc/nginx/sites-available/beachvolleybot.conf"

# Simple logger.
log() {
  printf "${BLUE}[release]${NC} %s\n" "$*"
}

# Success logger.
success() {
  printf "${GREEN}[release] %s${NC}\n" "$*"
}

# Warning logger.
warn() {
  printf "${YELLOW}[release] %s${NC}\n" "$*"
}

# Print error and stop execution.
fail() {
  printf "${RED}[release] ERROR: %s${NC}\n" "$*" >&2
  exit 1
}

# Ensure a required command exists in PATH.
require_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

# Run a command as the deploy user.
run_as_deploy() {
  local cmd="$1"
  runuser -u "$DEPLOY_USER" -- bash -lc "cd '$BASE_DIR' && $cmd"
}

# Run a command as the deploy user in a specific directory.
run_in_dir_as_deploy() {
  local workdir="$1"
  local cmd="$2"
  runuser -u "$DEPLOY_USER" -- bash -lc "cd '$workdir' && $cmd"
}

# If anything fails, show where the new bundle was left.
cleanup_on_error() {
  local exit_code=$?
  if [[ $exit_code -ne 0 ]]; then
    log "Release failed."
    if [[ -n "${NEW_BUNDLE_DIR:-}" && -d "${NEW_BUNDLE_DIR:-}" ]]; then
      log "New bundle remains at: ${NEW_BUNDLE_DIR}"
    fi
  fi
  exit "$exit_code"
}
trap cleanup_on_error ERR

# This script must be run as root.
# Root is needed for nginx config updates and service reload.
if [[ "${EUID}" -ne 0 ]]; then
  echo "Run release.sh as root." >&2
  exit 1
fi

# Verify required tools are installed.
require_cmd git
require_cmd make
require_cmd sed
require_cmd grep
require_cmd nginx
require_cmd systemctl
require_cmd runuser

# Sanity checks.
[[ -d "$BASE_DIR" ]] || fail "Base directory does not exist: $BASE_DIR"
[[ -f "$NGINX_CONF" ]] || fail "Nginx config not found: $NGINX_CONF"
id "$DEPLOY_USER" >/dev/null 2>&1 || fail "Deploy user not found: $DEPLOY_USER"

# Work from the script directory.
cd "$BASE_DIR"

# Find existing bundle directories like:
# bundle0, bundle1, bundle2, ...
# Sort them naturally so the highest bundle is the current/latest one.
mapfile -t bundle_names < <(
  find . -maxdepth 1 -mindepth 1 -type d -name 'bundle[0-9]*' -printf '%f\n' | sort -V
)

# At least one existing bundle is required because:
# - old worker must later be stopped
[[ ${#bundle_names[@]} -gt 0 ]] || fail "No existing bundles found in $BASE_DIR"

# Highest existing bundle becomes the previous/current release.
PREV_BUNDLE="${bundle_names[-1]}"
PREV_BUNDLE_NUM="${PREV_BUNDLE#bundle}"

# New bundle number is previous + 1.
NEW_BUNDLE_NUM=$((PREV_BUNDLE_NUM + 1))
NEW_BUNDLE="bundle${NEW_BUNDLE_NUM}"

# bundle0 is the canonical source of configuration for all releases.
CONFIG_SOURCE_BUNDLE="bundle0"
CONFIG_SOURCE_DIR="${BASE_DIR}/${CONFIG_SOURCE_BUNDLE}"

# Absolute paths for old and new bundles.
NEW_BUNDLE_DIR="${BASE_DIR}/${NEW_BUNDLE}"
PREV_BUNDLE_DIR="${BASE_DIR}/${PREV_BUNDLE}"

log "Previous bundle: ${PREV_BUNDLE}"
log "New bundle: ${NEW_BUNDLE}"

# Refuse to continue if target directory already exists.
[[ ! -e "$NEW_BUNDLE_DIR" ]] || fail "Target bundle already exists: $NEW_BUNDLE_DIR"

# Clone the repo into the new bundle directory as the deploy user.
log "Cloning repository into ${NEW_BUNDLE_DIR}"
run_as_deploy "git clone '$REPO_URL' '$NEW_BUNDLE_DIR'"

# Copy config from bundle0 so secrets and env-specific settings persist.
# bundle0 is the canonical source of truth and must never be overwritten by later releases.
[[ -d "${CONFIG_SOURCE_DIR}/config" ]] || fail "Source config directory missing: ${CONFIG_SOURCE_DIR}/config"
[[ -d "${NEW_BUNDLE_DIR}/config" ]] || fail "Target config directory missing: ${NEW_BUNDLE_DIR}/config"

log "Copying config/ from ${CONFIG_SOURCE_BUNDLE} to ${NEW_BUNDLE}"
run_as_deploy "cp -a '$CONFIG_SOURCE_DIR/config/.' '$NEW_BUNDLE_DIR/config/'"

# The repo is expected to contain install.sh.
[[ -f "${NEW_BUNDLE_DIR}/install.sh" ]] || fail "install.sh not found in ${NEW_BUNDLE_DIR}"

# Run project installation/setup inside the new bundle as the deploy user.
# install.sh handles: composer install, runtime dirs, migrations, tests, and queue worker start.
log "Running install.sh"
run_in_dir_as_deploy "$NEW_BUNDLE_DIR" "bash ./install.sh"

# Keep a backup of nginx config in case validation fails and rollback is needed.
log "Updating nginx config to point production to ${NEW_BUNDLE}"
NGINX_BACKUP="$(mktemp)"
cp "$NGINX_CONF" "$NGINX_BACKUP"

# Replace any production bundle path in nginx config:
# /var/www/beachvolleybot-production/public/bundleN/
# ->
# /var/www/beachvolleybot-production/public/bundleX/
sed -E -i \
  "s#/var/www/beachvolleybot-production/public/bundle[0-9]+/#/var/www/beachvolleybot-production/public/${NEW_BUNDLE}/#g" \
  "$NGINX_CONF"

# Validate nginx config before reload.
log "Validating nginx config"
if ! nginx -t; then
  log "Nginx config test failed. Restoring previous config."
  cp "$NGINX_BACKUP" "$NGINX_CONF"
  nginx -t || true
  fail "Aborting release because nginx config is invalid"
fi

# Reload nginx to activate the new bundle.
log "Reloading nginx"
systemctl reload nginx

# After traffic is switched, stop workers for the previous bundle.
log "Stopping workers for ${PREV_BUNDLE}"
run_in_dir_as_deploy "$PREV_BUNDLE_DIR" "make workers-stop"

# Remove temporary nginx backup.
rm -f "$NGINX_BACKUP"

echo
printf "${GREEN}===== RELEASE RESULT =====${NC}\n"
printf "${GREEN}Status:${NC} SUCCESS\n"
printf "${GREEN}Previous bundle:${NC} %s\n" "$PREV_BUNDLE"
printf "${GREEN}Active bundle:${NC} %s\n" "$NEW_BUNDLE"
printf "${GREEN}New bundle path:${NC} %s\n" "$NEW_BUNDLE_DIR"
printf "${GREEN}==========================${NC}\n"

success "Release completed successfully"
success "Active bundle: ${NEW_BUNDLE}"