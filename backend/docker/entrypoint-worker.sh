#!/bin/sh
# =============================================================================
# entrypoint-worker.sh — Production Horizon worker entrypoint
#
# Waits for the app container to finish bootstrapping (migrations),
# then starts Laravel Horizon with graceful shutdown support.
# =============================================================================
set -e

APP_DIR=/var/www/html

# Copy .env if not present (e.g. first boot without secrets manager)
if [ ! -f "${APP_DIR}/.env" ]; then
    if [ -f "${APP_DIR}/.env.example" ]; then
        cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    fi
fi

# Wait for the main app container to finish running migrations
# (postgres may be healthy but migrations not yet applied)
echo "[worker] Waiting for application bootstrap..."
TIMEOUT=60
ELAPSED=0
until php "${APP_DIR}/artisan" migrate:status --no-interaction >/dev/null 2>&1; do
    if [ "${ELAPSED}" -ge "${TIMEOUT}" ]; then
        echo "[worker] ERROR: Timed out waiting for migrations. Exiting." >&2
        exit 1
    fi
    sleep 2
    ELAPSED=$((ELAPSED + 2))
done
echo "[worker] Application ready. Starting Horizon..."

# Trap SIGTERM for graceful shutdown (Horizon drains current jobs before exit)
trap 'echo "[worker] SIGTERM received — terminating Horizon gracefully..."; php "${APP_DIR}/artisan" horizon:terminate; wait' TERM INT

exec php "${APP_DIR}/artisan" horizon &
wait $!
