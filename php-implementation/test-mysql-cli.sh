#!/usr/bin/env bash
set -euo pipefail

# Install mysql client if not present
if ! command -v mysql >/dev/null 2>&1; then
  apt-get update && apt-get install -y default-mysql-client
fi

# Start the mock data server
php php-implementation/run-mock-data.php > /tmp/mysql-proxy.log 2>&1 &
SERVER_PID=$!
trap "kill $SERVER_PID" EXIT

# Wait a moment for the server to be ready
sleep 1

# Run a simple query via mysql CLI and ensure we get the mock data back
OUTPUT=$(mysql -h127.0.0.1 -P3306 -u root -e "SELECT 1" 2>/tmp/mysql-client.log)

echo "$OUTPUT"
echo "$OUTPUT" | grep -q hello
