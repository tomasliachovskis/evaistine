#!/bin/bash
# Waits (up to 5 min) until the Sail container's clock matches the Mac's.
# The Docker VM clock stops while the Mac sleeps and catches up some time after
# the wake. A scraper started before that sees the jump as elapsed time, and its
# Process timeout kills it: 2026-10-16 the eVaistine nightly run started at 00:05
# right after a wake, the container still said 22:17, and 46 s later Eurovaistinė
# ("1h47m") and the three product-page runs were all killed at once.
# Called by the launchd scripts before their first artisan command.
container=vaistines-laravel.test-1
for i in $(seq 1 60); do
    drift=$(( $(date +%s) - $(docker exec "$container" date +%s 2>/dev/null || echo 0) ))
    if [ "${drift#-}" -le 5 ]; then
        [ "$i" -gt 1 ] && echo "Docker clock caught up after $(( (i - 1) * 5 ))s."
        exit 0
    fi
    [ "$i" = 1 ] && echo "Docker clock is ${drift}s off the Mac's, waiting for it to catch up..."
    sleep 5
done
echo "Docker clock still ${drift}s off after 5 min, starting anyway."
