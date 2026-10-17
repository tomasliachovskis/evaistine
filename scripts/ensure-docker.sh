#!/bin/bash
# Makes sure the Docker daemon answers and this repo's Sail stack is up.
# 2026-10-17 04:09 dockerd panicked (nil pointer in Daemon.ProcessEvent) under
# the eVaistine product-page run: Docker Desktop sat on its "Something went
# wrong" dialog until morning, the rest of the run and product-facts:batch-submit
# only printed "Docker is not running". A dead daemon gets Docker Desktop killed
# and started again here.
# Exit 0: all fine, 2: Docker had to be restarted (now fine), 1: still down.
# Called by scripts/nightly-scrape.sh from the repo root before every step.
container=vaistines-laravel.test-1
# Shared by both sites' nightly scripts, so only one of them restarts Docker.
lock=/tmp/nightly-docker-restart.lock

docker_up() {
    # A slow answer under load isn't a dead daemon: three tries.
    for i in 1 2 3; do
        docker info >/dev/null 2>&1 && return 0
        sleep 10
    done
    return 1
}

wait_for_docker() {
    for i in $(seq 1 60); do
        docker info >/dev/null 2>&1 && return 0
        sleep 5
    done
    return 1
}

rc=0
if ! docker_up; then
    rc=2
    find /tmp -maxdepth 1 -name "$(basename "$lock")" -mmin +15 -exec rmdir {} \; 2>/dev/null
    if mkdir "$lock" 2>/dev/null; then
        echo "Docker is not answering, restarting Docker Desktop..."
        # After a panic the app is still running, only its error dialog is up.
        osascript -e 'quit app "Docker"' >/dev/null 2>&1
        sleep 15
        pkill -f '/Applications/Docker.app/' 2>/dev/null
        sleep 5
        pkill -9 -f '/Applications/Docker.app/' 2>/dev/null
        sleep 2
        open -a Docker
        wait_for_docker
        up=$?
        rmdir "$lock"
    else
        echo "Docker is not answering, the other nightly script is restarting it, waiting..."
        wait_for_docker
        up=$?
    fi
    if [ "$up" != 0 ]; then
        echo "Docker still not answering after 5 min."
        exit 1
    fi
    echo "Docker is back."
fi

if [ -z "$(docker ps -q -f name="$container")" ]; then
    echo "Starting the Sail stack..."
    ./vendor/bin/sail up -d
    sleep 20
fi
exit $rc
