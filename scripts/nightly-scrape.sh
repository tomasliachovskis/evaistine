#!/bin/bash
# Nightly eVaistine.lt scrape from the owner's Mac (launchd:
# scripts/launchd/lt.evaistine.nightly-scrape.plist, 00:00; install it with
# cp scripts/launchd/lt.evaistine.nightly-scrape.plist ~/Library/LaunchAgents/
# && launchctl load -w ~/Library/LaunchAgents/lt.evaistine.nightly-scrape.plist).
# The same setup as superakcijos (nuolaidos repo, scripts/nightly-scrape.sh,
# 01:00); the two runs overlap, which is fine (different sites).
# The scrapers post to production through SCRAPER_API_URL in .env.
# Not the Laravel scheduler in the container: it only runs while the Mac is
# awake, so a sleeping Mac skipped the whole night (2026-10-15).
# Steps: scrapers:run --all, flyers:scrape --all, and on Mondays
# hours:scrape --all. The long runs stop themselves at 08:00
# (App\Support\QuietHours).
# Logs: each night's full output goes to storage/logs/nightly/YYYY-MM-DD.log
# (kept 30 days), ending with a per-store OK/WARN/FAILED summary; the
# WARN/FAILED lines are also appended to storage/logs/nightly/problems.log, one
# history of what went wrong each night. The launchd log
# (~/Library/Logs/evaistine-nightly-scrape.log) only catches crashes before
# that redirect.
# NIGHTLY_DRY=1 only checks that sail works.
export PATH="/Applications/Docker.app/Contents/Resources/bin:/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin"
cd /Users/tomas/www/vaistines || exit 1

# Keep the Mac awake for the whole run. Without it a closed-lid Mac sleeps
# through the night and only runs in ~45s maintenance wakes every ~17 min.
# -s holds the system awake on AC power, also with the lid closed. Waking the
# Mac is `pmset repeat wakeorpoweron` (00:55, set once for both sites); when
# it sleeps at 00:00, launchd starts this job at the next wake.
if [ -z "$NIGHTLY_CAFFEINATED" ]; then
    export NIGHTLY_CAFFEINATED=1
    exec /usr/bin/caffeinate -s -i /bin/bash "$0" "$@"
fi

LOG_DIR=/Users/tomas/www/vaistines/storage/logs/nightly
LOG="$LOG_DIR/$(date '+%F').log"
mkdir -p "$LOG_DIR"
find "$LOG_DIR" -name '20*.log' -mtime +30 -delete
# Where this run starts in the day's log, so a second run that day only reports its own problems.
LOG_START=$(( $(cat "$LOG" 2>/dev/null | wc -l) + 1 ))
exec >>"$LOG" 2>&1

echo "=== $(date '+%F %T') start"
if ! docker info >/dev/null 2>&1; then
    echo "Docker is not running, nothing done."
    echo "== $(date '+%F') FAILED   Docker is not running" >>"$LOG_DIR/problems.log"
    exit 1
fi
if [ -z "$(docker ps -q -f name=vaistines-laravel.test-1)" ]; then
    echo "Starting the vaistines Sail stack..."
    ./vendor/bin/sail up -d
    sleep 20
fi

if [ "$NIGHTLY_DRY" = "1" ]; then
    ./vendor/bin/sail artisan --version
    echo "=== $(date '+%F %T') dry run done"
    exit 0
fi

scripts/wait-for-docker-clock.sh

# Each step runs even if a store in the previous one failed.
./vendor/bin/sail artisan scrapers:run --all
rc=$?
echo "--- $(date '+%F %T') scrapers:run exit $rc"
./vendor/bin/sail artisan flyers:scrape --all
rc=$?
echo "--- $(date '+%F %T') flyers:scrape exit $rc"
# One more try for every store that failed above (a timeout on the store's
# site, e.g. Maxima 2026-10-16), now that the flyers gave it some time.
./vendor/bin/sail artisan scrapers:run --failed
rc=$?
echo "--- $(date '+%F %T') scrapers:run --failed exit $rc"
if [ "$(date '+%u')" = "1" ]; then
    ./vendor/bin/sail artisan hours:scrape --all
    rc=$?
    echo "--- $(date '+%F %T') hours:scrape exit $rc"
fi
echo "=== $(date '+%F %T') done"

# One line per WARN/FAILED/SKIPPED store from the summaries (the "- ..."
# detail lines stay in the night's own log).
problems=$(tail -n +"$LOG_START" "$LOG" | grep -E '^(WARN|FAILED|SKIPPED) ')
if [ -n "$problems" ]; then
    { echo "== $(date '+%F') (details in $(basename "$LOG"))"; echo "$problems"; } >>"$LOG_DIR/problems.log"
else
    echo "== $(date '+%F') all OK" >>"$LOG_DIR/problems.log"
fi
