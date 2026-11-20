<?php
$dbConfig = require __DIR__ . '/../config.php';

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['dbname']
);

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="en">
    <head><meta charset="utf-8"><title>Database error</title>
    <link rel="stylesheet" href="assets/style.css"></head>
    <body>
    <div class="wrap page">
      <div class="notice error">
        <strong>Could not connect to the database.</strong><br>
        Check the settings in <code>config.php</code> and make sure PostgreSQL is running.
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}
