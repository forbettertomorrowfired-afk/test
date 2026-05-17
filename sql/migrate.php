<?php
/**
 * AtomQuest — Database Migration Runner
 * Runs numbered SQL files in order, tracking applied ones in _migrations table.
 * Usage: php sql/migrate.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = get_db();

// Ensure _migrations table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
    id SERIAL PRIMARY KEY,
    filename VARCHAR(255) UNIQUE NOT NULL,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
)");

// Get already applied migrations
$applied = [];
$stmt = $pdo->query("SELECT filename FROM _migrations ORDER BY filename");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $applied[] = $row['filename'];
}

// Find all numbered SQL files
$sql_dir = __DIR__;
$files = glob($sql_dir . '/[0-9]*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $filename = basename($file);
    if (in_array($filename, $applied)) {
        echo "[SKIP] $filename (already applied)\n";
        continue;
    }

    echo "[APPLYING] $filename ... ";
    $sql = file_get_contents($file);

    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare("INSERT INTO _migrations (filename) VALUES (?)");
        $stmt->execute([$filename]);
        echo "OK\n";
        $count++;
    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nDone. Applied $count migration(s).\n";
