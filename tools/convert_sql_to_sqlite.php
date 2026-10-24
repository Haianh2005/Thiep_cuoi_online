<?php
// tools/convert_sql_to_sqlite.php
declare(strict_types=1);

$sqlPath = __DIR__ . '/../wedding_db.sql';
$sqlitePath = __DIR__ . '/../wedding.sqlite';

if (!file_exists($sqlPath)) {
    die("Error: wedding_db.sql not found at $sqlPath\n");
}

if (file_exists($sqlitePath)) {
    unlink($sqlitePath);
}

$sqlite = new PDO("sqlite:" . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->exec("PRAGMA foreign_keys = OFF;");
$sqlite->exec("PRAGMA journal_mode = MEMORY;");
$sqlite->exec("PRAGMA synchronous = OFF;");

echo "Reading SQL file...\n";
$lines = file($sqlPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$currentStmt = '';
$inInsert = false;
$tableCount = 0;
$rowCount = 0;

$sqlite->beginTransaction();

foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, 'SET ')) {
        continue;
    }

    $currentStmt .= $line . "\n";

    if (str_ends_with(rtrim($trimmed), ';')) {
        $stmtToRun = trim($currentStmt);
        $currentStmt = '';

        if (stripos($stmtToRun, 'DROP TABLE') === 0) {
            // Run DROP TABLE directly
            $sqlite->exec($stmtToRun);
        } elseif (stripos($stmtToRun, 'CREATE TABLE') === 0) {
            // Convert MySQL CREATE TABLE to SQLite
            $converted = convertCreateTableToSqlite($stmtToRun);
            $sqlite->exec($converted);
            $tableCount++;
            echo "Created table: " . extractTableName($stmtToRun) . "\n";
        } elseif (stripos($stmtToRun, 'INSERT INTO') === 0) {
            // Convert MySQL escapes (\' to '')
            $converted = convertInsertToSqlite($stmtToRun);
            $sqlite->exec($converted);
            $rowCount++;
        }
    }
}

$sqlite->commit();
echo "Conversion complete!\n";
echo "Tables created: $tableCount\n";
echo "INSERT blocks executed: $rowCount\n";
echo "SQLite file size: " . round(filesize($sqlitePath) / 1024, 2) . " KB\n";

function extractTableName(string $sql): string {
    if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $sql, $m)) {
        return $m[1];
    }
    return 'unknown';
}

function convertCreateTableToSqlite(string $sql): string {
    // Remove ENGINE=... DEFAULT CHARSET=...
    $sql = preg_replace('/\)\s*ENGINE=.*?;/is', ');', $sql);
    
    // Convert int(11) NOT NULL AUTO_INCREMENT -> INTEGER PRIMARY KEY AUTOINCREMENT
    $sql = preg_replace('/`id`\s+int\(\d+\)\s+NOT\s+NULL\s+AUTO_INCREMENT/i', '`id` INTEGER PRIMARY KEY AUTOINCREMENT', $sql);

    // Remove standalone PRIMARY KEY (`id`) if id was already declared primary key
    $sql = preg_replace('/,\s*PRIMARY\s+KEY\s*\(`id`\)/i', '', $sql);

    // Convert types
    $sql = preg_replace('/\bint\(\d+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\btinyint\(\d+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bvarchar\(\d+\)/i', 'TEXT', $sql);
    $sql = preg_replace('/\benum\([^)]+\)/i', 'TEXT', $sql);
    $sql = preg_replace('/\bdate\b/i', 'TEXT', $sql);
    $sql = preg_replace('/\btime\b/i', 'TEXT', $sql);
    $sql = preg_replace('/\btimestamp\b/i', 'TEXT', $sql);
    $sql = preg_replace('/current_timestamp\(\)\s*ON\s*UPDATE\s*current_timestamp\(\)/i', 'CURRENT_TIMESTAMP', $sql);
    $sql = preg_replace('/current_timestamp\(\)/i', 'CURRENT_TIMESTAMP', $sql);

    // Remove foreign key constraints and key lines for SQLite simplicity
    $lines = explode("\n", $sql);
    $cleanLines = [];
    foreach ($lines as $l) {
        $tr = trim($l);
        if (preg_match('/^(CONSTRAINT|FOREIGN KEY|KEY\s|UNIQUE KEY\s)/i', $tr)) {
            continue;
        }
        $cleanLines[] = $l;
    }
    $res = implode("\n", $cleanLines);
    // Fix any trailing commas before closing paren
    $res = preg_replace('/,(\s*\)\s*;)/', '$1', $res);
    return $res;
}

function convertInsertToSqlite(string $sql): string {
    // MySQL uses \' to escape quotes in string literals, while SQLite uses ''
    // We replace \' with '' except when preceded by an odd number of backslashes
    // Quick regex for \' -> ''
    $sql = preg_replace("/(?<!\\\\)\\\\'/", "''", $sql);
    // Also replace \" with "
    $sql = preg_replace('/\\\\\"/', '"', $sql);
    return $sql;
}
