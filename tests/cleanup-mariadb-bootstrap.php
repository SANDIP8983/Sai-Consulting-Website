<?php

// Deliberately independent of .env, mysql client defaults, and inherited DB URLs.
const CLEANUP_MARIADB_DSN = 'mysql:host=127.0.0.1;port=3306;dbname=sai_cleanup_test;charset=utf8mb4';
const CLEANUP_MARIADB_DATABASE = 'sai_cleanup_test';

function cleanupMariaDbConnection(): PDO
{
    $pdo = new PDO(CLEANUP_MARIADB_DSN, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $identity = $pdo->query('SELECT DATABASE() AS db, VERSION() AS version, @@port AS port, @@datadir AS datadir, @@hostname AS hostname')->fetch(PDO::FETCH_ASSOC);
    $directory = strtolower(rtrim(str_replace('\\', '/', $identity['datadir']), '/'));
    if ($identity['db'] !== CLEANUP_MARIADB_DATABASE || (int) $identity['port'] !== 3306
        || ! str_starts_with($identity['version'], '10.4.32-MariaDB')
        || $directory !== 'c:/xampp/mysql/data' || ! is_dir($directory)
        || strcasecmp($identity['hostname'], gethostname()) !== 0) {
        throw new RuntimeException('STOP: local XAMPP database isolation could not be verified.');
    }

    return $pdo;
}

function cleanupMariaDbBaseline(PDO $pdo): array
{
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'sai_cleanup_test' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $result = [];
    foreach ($tables as $table) {
        if (! preg_match('/^[a-z_0-9]+$/', $table)) {
            throw new RuntimeException('STOP: unexpected test table identifier.');
        }
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $encoded = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
        sort($encoded);
        $result[$table] = hash('sha256', json_encode([$columns, $encoded], JSON_THROW_ON_ERROR));
    }

    return $result;
}

$manifest = sys_get_temp_dir().'/sai-cleanup-mariadb-baseline-'.hash('sha256', dirname(__DIR__)).'.json';
define('CLEANUP_MARIADB_MANIFEST', $manifest);
try {
    $pdo = cleanupMariaDbConnection();
    foreach (['ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA', 'TRIGGERS' => 'TRIGGER_SCHEMA'] as $table => $column) {
        if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.$table WHERE $column = 'sai_cleanup_test'")->fetchColumn() !== 0) {
            throw new RuntimeException('STOP: unexpected routines, events or triggers in the test database.');
        }
    }
    $baseline = cleanupMariaDbBaseline($pdo);
    if ($baseline !== []) {
        // Repeat runs are allowed only after exact restoration to the recorded
        // post-migration baseline from an initially verified empty database.
        $recorded = is_file($manifest) ? json_decode(file_get_contents($manifest), true) : null;
        if (! is_array($recorded) || ($recorded['database'] ?? null) !== CLEANUP_MARIADB_DATABASE
            || ($recorded['baseline'] ?? null) !== $baseline) {
            throw new RuntimeException('STOP: test database is nonempty and not the exact verified synthetic-test baseline. No writes allowed.');
        }
    }
    define('CLEANUP_MARIADB_INITIAL_EMPTY', $baseline === []);
    $pdo = null;
} catch (Throwable) {
    fwrite(STDERR, "STOP: local test database isolation/baseline verification failed. No migrations or fixture writes performed.\n");
    exit(2);
}

$isolated = [
    'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('m', 32)),
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/sai-mariadb-no-config-'.bin2hex(random_bytes(12)),
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
    'DB_DATABASE' => CLEANUP_MARIADB_DATABASE, 'DB_USERNAME' => 'root', 'DB_PASSWORD' => '', 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'database',
    'DB_QUEUE_CONNECTION' => 'mysql', 'DB_QUEUE_TABLE' => 'jobs', 'QUEUE_FAILED_DRIVER' => 'database-uuids',
    'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null',
];
foreach ($isolated as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
require dirname(__DIR__).'/vendor/autoload.php';
