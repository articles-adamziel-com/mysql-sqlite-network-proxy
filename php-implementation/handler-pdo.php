<?php

class PDOHandler implements MySQLQueryHandler {
	private $pdo;

	public function __construct($pdo) {
		$this->pdo = $pdo;
	}

        public function handleQuery(string $query): MySQLServerQueryResult {
                try {
                        $stmt = $this->pdo->prepare($query);
                        $stmt->execute();

                        if ($stmt->columnCount() > 0) {
                                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                $columns = $this->computeColumnInfo($rows);
                                return new SelectQueryResult($columns, $rows);
                        }

                        return new OkayPacketResult(
                                $stmt->rowCount(),
                                (int)($this->pdo->lastInsertId() ?: 0)
                        );
                } catch (\Throwable $e) {
                        return new ErrorQueryResult($e->getMessage());
                }
        }

	public function computeColumnInfo($rows) {
		if (empty($rows)) {
			return [];
		}
	
		$columns = [];
		$firstRow = $rows[0];
		
		foreach ($firstRow as $key => $value) {
			$columnType = 8;  // Default to LONGLONG
			$columnLength = 1;
			$decimals = 0;
			
			// Analyze all rows to find the maximum length and most specific type
			foreach ($rows as $row) {
				$currentValue = $row[$key];
				
				if (is_string($currentValue)) {
					$columnType = 253;  // VARCHAR
					$columnLength = max($columnLength, strlen($currentValue));
				} elseif (is_numeric($currentValue)) {
					if (is_int($currentValue) || $currentValue == (int)$currentValue) {
						if ($columnType != 253) { // Don't override VARCHAR
							$columnType = 3;   // LONG
							$columnLength = 11;
						}
					} else {
						if ($columnType != 253) { // Don't override VARCHAR
							$columnType = 246; // DECIMAL
							$columnLength = 10;
							$decimals = 2;
						}
					}
				}
			}
			
			$columns[] = [
				'name' => $key,
				'length' => $columnLength ?? 1,
				'type' => $columnType,
				'flags' => 129,
				'decimals' => $decimals
			];
		}
		return $columns;
	}
}
