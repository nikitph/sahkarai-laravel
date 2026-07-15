#!/usr/bin/env bash
set -euo pipefail

url="${1:-http://127.0.0.1:8002/conformance/sse}"
started=$(date +%s)
ticks=0

while IFS= read -r line; do
    case "$line" in
        "event: tick") ticks=$((ticks + 1)) ;;
    esac
done < <(curl -fsSN --no-buffer "$url")

elapsed=$(( $(date +%s) - started ))

if [ "$ticks" -ne 5 ] || [ "$elapsed" -lt 1 ]; then
    printf 'SSE conformance failed: ticks=%s elapsed=%ss\n' "$ticks" "$elapsed" >&2
    exit 1
fi

printf 'SSE conformance passed: ticks=%s elapsed=%ss\n' "$ticks" "$elapsed"
