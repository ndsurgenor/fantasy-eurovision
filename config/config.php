<?php

// Load .env file from project root if it exists
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value, " \t\"'"); // strip surrounding quotes
        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

define('DB_HOST',   $_ENV['DB_HOST']   ?? 'localhost');
define('DB_NAME',   $_ENV['DB_NAME']   ?? 'fantasyeurovision_db');
define('DB_USER',   $_ENV['DB_USER']   ?? 'root');
define('DB_PASS',   $_ENV['DB_PASS']   ?? '');
define('SITE_URL',  $_ENV['SITE_URL']  ?? 'http://localhost:8000');
define('SITE_NAME', $_ENV['SITE_NAME'] ?? 'Fantasy Eurovision');
