#!/bin/bash
# Nightly eVaistine.lt scrape from the owner's Mac (launchd:
# scripts/launchd/lt.evaistine.nightly-scrape.plist, 00:01; install it with
# cp scripts/launchd/lt.evaistine.nightly-scrape.plist ~/Library/LaunchAgents/
# && launchctl load -w ~/Library/LaunchAgents/lt.evaistine.nightly-scrape.plist).
# The same setup as superakcijos (nuolaidos repo, scripts/nightly-scrape.sh,
# 01:00); the two runs overlap, which is fine (different sites). The Mac is
# woken at 00:00 (pmset repeat), this starts a minute later.
# The scrapers post to production through SCRAPER_API_URL in .env.
# Not the Laravel scheduler in the container: it only runs while the Mac is
# awake, so a sleeping Mac skipped the whole night (2026-10-15).
# Steps: scrapers:run --all, flyers:scrape --all, scrapers:run --failed, on
# Mondays hours:scrape --all, then the product pages until 07:30 and
# product-facts:batch-submit. The long runs stop themselves at 08:00
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
# Mac is `pmset repeat wakeorpoweron` (00:00, set once for both sites).
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

# One line per WARN/FAILED/SKIPPED store from the summaries (the "- ..."
# detail lines stay in the night's own log).
report_problems() {
    problems=$(tail -n +"$LOG_START" "$LOG" | grep -E '^(WARN|FAILED|SKIPPED) ')
    if [ -n "$problems" ]; then
        { echo "== $(date '+%F') (details in $(basename "$LOG"))"; echo "$problems"; } >>"$LOG_DIR/problems.log"
    else
        echo "== $(date '+%F') all OK" >>"$LOG_DIR/problems.log"
    fi
}

# Before every step: a dead daemon (dockerd panic 2026-10-17 04:09) gets
# Docker restarted (scripts/ensure-docker.sh); the FAILED line lands in
# problems.log either way. Gives up on the night if Docker stays down.
ensure_docker() {
    scripts/ensure-docker.sh
    case $? in
        0) return 0 ;;
        2) echo "FAILED   Docker  daemon was down, restarted at $(date '+%T')"; return 0 ;;
    esac
    echo "FAILED   Docker  daemon down at $(date '+%T'), could not restart it, rest of the night skipped"
    echo "=== $(date '+%F %T') done"
    report_problems
    exit 1
}

echo "=== $(date '+%F %T') start"
ensure_docker

if [ "$NIGHTLY_DRY" = "1" ]; then
    ./vendor/bin/sail artisan --version
    echo "=== $(date '+%F %T') dry run done"
    exit 0
fi

scripts/wait-for-docker-clock.sh

# Each step runs even if a store in the previous one failed.
ensure_docker
./vendor/bin/sail artisan scrapers:run --all
rc=$?
echo "--- $(date '+%F %T') scrapers:run exit $rc"
ensure_docker
./vendor/bin/sail artisan flyers:scrape --all
rc=$?
echo "--- $(date '+%F %T') flyers:scrape exit $rc"
# One more try for every store that failed above (a timeout on the store's
# site, e.g. Maxima 2026-10-16), now that the flyers gave it some time.
ensure_docker
./vendor/bin/sail artisan scrapers:run --failed
rc=$?
echo "--- $(date '+%F %T') scrapers:run --failed exit $rc"
if [ "$(date '+%u')" = "1" ]; then
    ensure_docker
    ./vendor/bin/sail artisan hours:scrape --all
    rc=$?
    echo "--- $(date '+%F %T') hours:scrape exit $rc"
fi
# Product pages (EANs and the "Apie prekę" text for the product facts,
# docs/product-facts.md), the three pharmacies side by side (different hosts),
# with whatever is left of the night until 07:30. Slow on purpose (one page at
# a time, 429s), so not part of the main scrape. Was a separate 18:30 job on
# the in-container scheduler until 2026-10-16: the closed-lid Mac slept and it
# crawled along in maintenance wakes. Then the night's Gemini batch.
# If Docker dies under the pages (2026-10-17 04:09, 3h of the budget unused),
# it is restarted and the pages get one more go with what's left.
pages_ran=0
for attempt in 1 2; do
    minutes=$(( ($(date -j -f '%H:%M:%S' '07:30:00' +%s) - $(date +%s)) / 60 ))
    if [ "$minutes" -lt 30 ]; then
        echo "Only $minutes min left before 07:30, no more product pages tonight."
        break
    fi
    ensure_docker
    for pharmacy in gintarine-vaistine apotheka mano-vaistine; do
        ./vendor/bin/sail artisan scrapers:fetch-product-pages "$pharmacy" --minutes="$minutes" &
    done
    wait
    pages_ran=1
    echo "--- $(date '+%F %T') product pages done ($minutes min budget)"
    docker info >/dev/null 2>&1 && break
done
if [ "$pages_ran" = 1 ]; then
    ensure_docker
    ./vendor/bin/sail artisan product-facts:batch-submit
    echo "--- $(date '+%F %T') product-facts:batch-submit exit $?"
fi
echo "=== $(date '+%F %T') done"
report_problems
