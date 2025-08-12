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

# Run a series of queries via mysql CLI to prove more advanced support
OUTPUT=$(mysql -h127.0.0.1 -P3306 -u root 2>/tmp/mysql-client.log <<'SQL'
SELECT 1;
BEGIN;
ROLLBACK;
BEGIN;
COMMIT;
CREATE TABLE t (id INT);
INSERT INTO t VALUES (1);
UPDATE t SET id = 2 WHERE id = 1;
DELETE FROM t WHERE id = 2;
ALTER TABLE t ADD COLUMN name TEXT;
DROP TABLE t;
SET @foo := 'bar';
SELECT @foo;
SQL
)

echo "$OUTPUT"
echo "$OUTPUT" | grep -q 1
echo "$OUTPUT" | grep -q bar
