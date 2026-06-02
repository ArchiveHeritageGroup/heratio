<?php

// Debug script to check database connection behavior
echo "Current database config debug\n";

// Check if DB_SOCKET is set to empty string
echo "DB_SOCKET = '" . getenv('DB_SOCKET') . "'\n";
echo "DB_HOST = '" . getenv('DB_HOST') . "'\n";
echo "DB_CONNECTION = '" . getenv('DB_CONNECTION') . "'\n";
echo "DB_DATABASE = '" . getenv('DB_DATABASE') . "'\n";
echo "DB_USERNAME = '" . getenv('DB_USERNAME') . "'\n";
echo "DB_PASSWORD = '" . getenv('DB_PASSWORD') . "'\n";

// Print config array
$config = [
    'driver' => 'mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE') ?: 'laravel',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'unix_socket' => getenv('DB_SOCKET') ?: '',
];

echo "Config values:\n";
foreach ($config as $key => $value) {
    echo "$key: '$value'\n";
}

echo "End debug\n";
?>