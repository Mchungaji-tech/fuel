<?php

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

try {
    $pdo = Database::connection();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM drivers')->fetchColumn();
    echo "drivers={$count}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "DB TEST FAILED: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
