#!/bin/bash
# Evening eVaistine.lt product pages from the owner's Mac (launchd:
# scripts/launchd/lt.evaistine.evening-scrape.plist, 18:30; install it with
# cp scripts/launchd/lt.evaistine.evening-scrape.plist ~/Library/LaunchAgents/
# && launchctl load -w ~/Library/LaunchAgents/lt.evaistine.evening-scrape.plist).
# scrapers:fetch-product-pages for Gintarinė, Apotheka and Mano vaistinė side
# by side (different hosts, 300 min budget, done by ~23:30, before the 00:00
# nightly scrape hits the same sites), then product-facts:batch-submit.
# Was on the in-container scheduler until 2026-10-16: the closed-lid Mac slept
# from 19:00, the runs crawled along in ~45s maintenance wakes (300-550 pages
# in 5.5 h) and were killed at 00:05 when the Docker clock jumped forward.
# Logs: each pharmacy's own storage/logs/nightly/YYYY-MM-DD-product-pages-{pharmacy}.log
# (plus a WARN/FAILED block in problems.log), this script's output in
# storage/logs/nightly/YYYY-MM-DD-evening.log.
export PATH="/Applications/Docker.app/Contents/Resources/bin:/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin"
cd /Users/tomas/www/vaistines || exit 1

# Same as the nightly script: -s keeps a closed-lid Mac on AC awake.
if [ -z "$NIGHTLY_CAFFEINATED" ]; then
    export NIGHTLY_CAFFEINATED=1
    exec /usr/bin/caffeinate -s -i /bin/bash "$0" "$@"
fi

LOG_DIR=/Users/tomas/www/vaistines/storage/logs/nightly
LOG="$LOG_DIR/$(date '+%F')-evening.log"
mkdir -p "$LOG_DIR"
exec >>"$LOG" 2>&1

echo "=== $(date '+%F %T') start"
if ! docker info >/dev/null 2>&1; then
    echo "Docker is not running, nothing done."
    echo "== $(date '+%F') FAILED   evening: Docker is not running" >>"$LOG_DIR/problems.log"
    exit 1
fi
if [ -z "$(docker ps -q -f name=vaistines-laravel.test-1)" ]; then
    echo "Starting the vaistines Sail stack..."
    ./vendor/bin/sail up -d
    sleep 20
fi
scripts/wait-for-docker-clock.sh

# A Mac that slept through 18:30 starts this at its next wake; after 23:00
# that would run into the nightly scrape on the same sites, so skip the night.
if [ "$(date '+%H')" -lt 18 ] || [ "$(date '+%H')" -ge 23 ]; then
    echo "Started at $(date '+%H:%M'), outside 18:00-23:00, skipping tonight."
    echo "== $(date '+%F') SKIPPED  evening product pages (started $(date '+%H:%M'))" >>"$LOG_DIR/problems.log"
    exit 0
fi

for pharmacy in gintarine-vaistine apotheka mano-vaistine; do
    ./vendor/bin/sail artisan scrapers:fetch-product-pages "$pharmacy" --minutes=300 &
done
wait
echo "--- $(date '+%F %T') product pages done"
./vendor/bin/sail artisan product-facts:batch-submit
echo "--- $(date '+%F %T') product-facts:batch-submit exit $?"
echo "=== $(date '+%F %T') done"
