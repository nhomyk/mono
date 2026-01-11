#!/usr/bin/env bash
set -euo pipefail

URL=${1:-http://127.0.0.1:8080/health}
MAX_WAIT=${2:-60}
SLEEP=1
elapsed=0

echo "Waiting for ${URL} (timeout ${MAX_WAIT}s)..."
while ! curl --silent --fail "$URL" >/dev/null 2>&1; do
  elapsed=$((elapsed + SLEEP))
  if [ "$elapsed" -ge "$MAX_WAIT" ]; then
    echo "Timed out waiting for ${URL} after ${MAX_WAIT}s"
    exit 1
  fi
  sleep "$SLEEP"
done
echo "${URL} is up"
