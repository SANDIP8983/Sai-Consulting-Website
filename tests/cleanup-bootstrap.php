<?php

// Dedicated, fail-closed runner: never load .env or a deployed configuration cache.
$isolated = [
    'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)),
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/sai-cleanup-no-config-'.bin2hex(random_bytes(12)),
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
    'DB_FOREIGN_KEYS' => 'true', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'database', 'DB_QUEUE_CONNECTION' => 'sqlite',
    'DB_QUEUE_TABLE' => 'jobs', 'QUEUE_FAILED_DRIVER' => 'database-uuids',
    'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null',
];
foreach ($isolated as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
require dirname(__DIR__).'/vendor/autoload.php';
