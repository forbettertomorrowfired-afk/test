#!/usr/bin/env bash
# start-db.sh — Start local PostgreSQL for AtomQuest development
# The socket is placed in /tmp/pg_runtime (avoids /run/postgresql permission issues)
# config.php is already set to DB_HOST='/tmp/pg_runtime'

set -euo pipefail
PGDATA="$(dirname "$0")/.pgdata"
SOCKET_DIR="/tmp/pg_runtime"
LOG="$PGDATA/logfile"

mkdir -p "$SOCKET_DIR"

STATUS=$(pg_ctl -D "$PGDATA" status 2>&1 || true)
if echo "$STATUS" | grep -q "server is running"; then
    echo "✅  PostgreSQL is already running."
else
    echo "🚀  Starting PostgreSQL..."
    pg_ctl -D "$PGDATA" -l "$LOG" -o "-k $SOCKET_DIR" start
    echo "✅  PostgreSQL started. Socket: $SOCKET_DIR"
fi

echo "   DB: atomquest  |  User: pranav  |  Socket: $SOCKET_DIR"
echo "   psql -h $SOCKET_DIR -d atomquest"
