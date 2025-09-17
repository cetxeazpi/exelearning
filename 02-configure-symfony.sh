#!/bin/sh
#
# Symfony configuration script
#
set -eo pipefail

# Colors for messages
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Log functions
log()  { printf "%b%s%b\n" "${GREEN}" "$1" "${NC}"; }
err()  { printf "%b%s%b\n" "${RED}"   "$1" "${NC}" 1>&2; }

# Function to check the availability of a database
check_db_availability() {
    local db_host="$1"
    local db_port="$2"
    log "Waiting for $db_host:$db_port to be ready..."
    while ! nc -w 1 "$db_host" "$db_port" > /dev/null 2>&1; do
        # Show some progress
        echo -n '.'
        sleep 1
    done
    log "\n\nGreat, $db_host is ready!"
}

# Assert that a required table exists (works with SQLite/MySQL/MariaDB/PostgreSQL)
assert_users_table_exists() {
    : "${REQUIRED_TABLE:=users}"
    : "${GREEN:=}"; : "${RED:=}"; : "${NC:=}"

    # If the table exists, this SELECT succeeds even if it's empty.
    if php bin/console dbal:run-sql "SELECT 1 FROM ${REQUIRED_TABLE} LIMIT 1" >/dev/null 2>&1; then
        printf "%bTable '%s' exists and is accessible.%b\n" "$GREEN" "$REQUIRED_TABLE" "$NC"
        return 0
    fi

    printf "%bTable '%s' not found or not accessible. Aborting.%b\n" "$RED" "$REQUIRED_TABLE" "$NC"
    exit 1
}


# --- Main ---

# If DB_HOST is set, check the availability of the database
if [ -n "$DB_HOST" ]; then
    check_db_availability "$DB_HOST" "$DB_PORT"
fi

# Execute pre-configuration commands if set
if [ -n "$PRE_CONFIGURE_COMMANDS" ]; then
    echo "Executing pre-configure commands..."
    eval "$PRE_CONFIGURE_COMMANDS"
fi

# If BASE_PATH is defined, generates the NGINX subdir.conf
if [ -n "$BASE_PATH" ] && [ -f "/etc/nginx/server-conf.d/subdir.conf.template" ]; then
    echo "Replacing subdir.conf.template with env var: $BASE_PATH"
    envsubst '\$BASE_PATH' < /etc/nginx/server-conf.d/subdir.conf.template > /etc/nginx/server-conf.d/subdir.conf
fi

# Update schema (kept for compatibility, but migrations are preferred)
log "Creating/updating database tables (schema:update)"
php bin/console doctrine:schema:update --force

# Run migrations
log "Running migrations"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --all-or-nothing

# Sync System Preferences definitions into DB (non-destructive)
log "Syncing System Preferences"
php bin/console app:prefs:sync || true

# Ensure persistent dir for system preferences files exists and expose it via symlink under public/
if [ -n "$FILES_DIR" ]; then
    log "Ensuring system preferences files directory"
    mkdir -p "$FILES_DIR/system_preferences_files" || true
    if [ -d /app/public ]; then
        ln -sfn "$FILES_DIR/system_preferences_files" /app/public/system_prefs || true
    fi
fi

# Fails the script if 'users' isn't there
assert_users_table_exists

# Create test user using environment variables
log "Creating test user"
php bin/console app:create-user "${TEST_USER_EMAIL}" "${TEST_USER_PASSWORD}" "${TEST_USER_USERNAME}" --no-fail

# Clear cache and configure other Symfony settings
log "Configuring other Symfony settings"
php bin/console cache:clear
php bin/console assets:install public

# Execute post-configuration commands if set
if [ -n "$POST_CONFIGURE_COMMANDS" ]; then
    echo "Executing post-configure commands..."
    eval "$POST_CONFIGURE_COMMANDS"
fi

log "Symfony has been successfully configured."

# Always return 0 (success)
exit 0
