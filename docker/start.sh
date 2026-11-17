#!/bin/sh
set -eu
PORT="${PORT:-80}"
case "$PORT" in *[!0-9]*|'') echo 'PORT must be numeric' >&2; exit 1;; esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then echo 'PORT out of range' >&2; exit 1; fi
export PORT
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
exec apache2-foreground
