<?php
/**
 * A MySQL proxy that always responds with the same, fixed data. It's a simple usage
 * example of the MySQLSocketServer class.
 */

require_once __DIR__ . '/mysql-server.php';

$server = new MySQLSocketServer(new class implements MySQLQueryHandler {
        /**
         * Very small in-memory store for user-defined variables.
         * All connections share the same store which is good enough for tests.
         */
        private array $vars = [];

        public function handleQuery(string $query): MySQLServerQueryResult {
                $trimmed = trim($query, " \t\n\r;");
                $lower = strtolower($trimmed);

                // Support setting user-defined variables like "SET @foo := 'bar'".
                if (preg_match('/^set\s+@([a-z0-9_]+)\s*:=\s*(.+)$/i', $trimmed, $m)) {
                        $value = trim($m[2], " '\"");
                        $this->vars[strtolower($m[1])] = $value;
                        return new OkayPacketResult(0, 0);
                }

                // Reading a previously set user-defined variable.
                if (preg_match('/^select\s+@([a-z0-9_]+)/i', $trimmed, $m)) {
                        $name = strtolower($m[1]);
                        $value = $this->vars[$name] ?? null;
                        $columns = [[
                                'name' => '@' . $name,
                                'type' => 0xfd,
                                'length' => 255,
                                'flags' => 0x0000,
                                'decimals' => 0,
                        ]];
                        return new SelectQueryResult($columns, [[ $value ]]);
                }

                // Simple constant SELECT queries like "SELECT 1".
                if (preg_match('/^select\s+1/i', $lower)) {
                        $columns = [[
                                'name' => '1',
                                'type' => 0x03, // MYSQL_TYPE_LONG
                                'length' => 11,
                                'flags' => 0x0000,
                                'decimals' => 0,
                        ]];
                        return new SelectQueryResult($columns, [[1]]);
                }

                if (!str_starts_with($lower, 'select')) {
                        // All other statements (DDL, DML, transactions, etc.) just return OK.
                        return new OkayPacketResult(0, 0);
                }

                // Default mock result set for ordinary SELECT queries.
                $columns = [[
                        'name' => 'text',
                        'type' => 0xfd,       // MYSQL_TYPE_VAR_STRING (VAR_STRING/BLOB)
                        'length' => 255,      // Max length for text
                        'flags' => 0x0000,    // No special flags
                        'decimals' => 0,
                ]];
                $rows_source = [
                        ['id' => 1, 'text' => 'hello'],
                        ['id' => 2, 'text' => 'world'],
                ];
                $rows = [];
                foreach ($rows_source as $row) {
                        $rowData = [];
                        foreach ($columns as $colMeta) {
                                $colName = $colMeta['name'];
                                $rowData[] = $row[$colName] ?? null;
                        }
                        $rows[] = $rowData;
                }
                return new SelectQueryResult($columns, $rows);
        }
}, ['port' => 3306]);

$server->start();
