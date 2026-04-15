#!/usr/bin/env bash

# Deploys a release as a new numbered bundle. Run as root, no arguments:
#
#   sudo /var/www/beachvolleybot-production/public/release.sh
#
#   /var/www/beachvolleybot-production/
#   ├── db/ logs/ queues/   <- shared state, located by config/paths.env
#   └── public/
#       ├── release.sh      <- this script
#       ├── bundle0/        <- set up by hand; every release copies its config/
#       └── bundle1/ ...    <- newest bundle; nginx /tg-bot points here
#
# Clones into the next bundle, copies bundle0's config/, runs install.sh, carries
# localization/missing.json over from the running bundle, repoints nginx, stops the
# old workers. Nothing switches until install.sh passes; failing before the switch
# rolls back to the previous bundle and leaves the new one behind, to delete before
# retrying. See README.md for the rest.

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

# How far the release got, for the exit handler to undo the right amount.
NGINX_BACKUP=""
NGINX_CONF_REWRITTEN=false
TRAFFIC_SWITCHED=false

# Set when a failed restore leaves the backup as the only copy of the old config.
KEEP_NGINX_BACKUP=false

# Put back the config the release rewrote. An empty backup would blank it instead.
restore_nginx_conf() {
  if [[ ! -s "$NGINX_BACKUP" ]]; then
    warn "Backup ${NGINX_BACKUP} is empty; ${NGINX_CONF} still points at ${NEW_BUNDLE}"

    return 1
  fi

  log "Restoring previous nginx config"
  cp "$NGINX_BACKUP" "$NGINX_CONF" || {
    warn "Could not restore ${NGINX_CONF} from ${NGINX_BACKUP}; it still points at ${NEW_BUNDLE}"

    return 1
  }
}

# Left alone, the workers install.sh started would keep consuming the shared
# queues while traffic stays on the previous bundle.
stop_new_bundle_workers() {
  [[ -f "${NEW_BUNDLE_DIR:-}/Makefile" ]] || return 0

  log "Stopping workers for ${NEW_BUNDLE}"
  run_in_dir_as_deploy "$NEW_BUNDLE_DIR" "make workers-stop" || warn "Could not stop workers in ${NEW_BUNDLE}"
}

# Undo a failed release — unless traffic already moved, where undoing it would
# take the bot down instead of saving it.
roll_back_release() {
  log "Release failed."

  if [[ "$TRAFFIC_SWITCHED" == true ]]; then
    warn "Nginx already serves ${NEW_BUNDLE}; leaving it and its workers running"
    warn "Workers for ${PREV_BUNDLE} may still run: 'make workers-stop' in ${PREV_BUNDLE_DIR}"

    return
  fi

  if [[ "$NGINX_CONF_REWRITTEN" == true ]]; then
    restore_nginx_conf || KEEP_NGINX_BACKUP=true
  fi

  stop_new_bundle_workers

  if [[ -d "${NEW_BUNDLE_DIR:-}" ]]; then
    log "New bundle remains at: ${NEW_BUNDLE_DIR}"
  fi
}

# Runs on every exit, ERR included, so the fail() paths roll back too.
on_exit() {
  local exit_code=$?
  trap - ERR EXIT

  if [[ $exit_code -ne 0 ]]; then
    roll_back_release
  fi

  if [[ -n "$NGINX_BACKUP" && "$KEEP_NGINX_BACKUP" == false ]]; then
    rm -f "$NGINX_BACKUP"
  fi

  exit "$exit_code"
}
trap on_exit ERR EXIT

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

# Carry the collected missing-translation report over from the running bundle.
# Only after install.sh: LocalizationFilesTest fails while missing.json exists.
# Never fatal, and no -p: php-fpm may own the file, and a report is worth no release.
MISSING_TRANSLATIONS_FILE="localization/missing.json"
if [[ -f "${PREV_BUNDLE_DIR}/${MISSING_TRANSLATIONS_FILE}" ]]; then
  log "Copying ${MISSING_TRANSLATIONS_FILE} from ${PREV_BUNDLE} to ${NEW_BUNDLE}"
  run_as_deploy "cp '${PREV_BUNDLE_DIR}/${MISSING_TRANSLATIONS_FILE}' '${NEW_BUNDLE_DIR}/${MISSING_TRANSLATIONS_FILE}'" \
    || warn "Could not copy ${MISSING_TRANSLATIONS_FILE}; it stays in ${PREV_BUNDLE}"
else
  log "No ${MISSING_TRANSLATIONS_FILE} in ${PREV_BUNDLE}, nothing to carry over"
fi

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
NGINX_CONF_REWRITTEN=true

# Validate nginx config before reload. Any failure from here on restores the backup.
log "Validating nginx config"
nginx -t || fail "Aborting release because nginx config is invalid"

# Reload nginx to activate the new bundle.
log "Reloading nginx"
systemctl reload nginx
TRAFFIC_SWITCHED=true

# After traffic is switched, stop workers for the previous bundle.
log "Stopping workers for ${PREV_BUNDLE}"
run_in_dir_as_deploy "$PREV_BUNDLE_DIR" "make workers-stop"

echo
printf "${GREEN}===== RELEASE RESULT =====${NC}\n"
printf "${GREEN}Status:${NC} SUCCESS\n"
printf "${GREEN}Previous bundle:${NC} %s\n" "$PREV_BUNDLE"
printf "${GREEN}Active bundle:${NC} %s\n" "$NEW_BUNDLE"
printf "${GREEN}New bundle path:${NC} %s\n" "$NEW_BUNDLE_DIR"
printf "${GREEN}==========================${NC}\n"

success "Release completed successfully"
success "Active bundle: ${NEW_BUNDLE}"