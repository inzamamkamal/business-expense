<?php

return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'database' => $_ENV['DB_NAME'] ?? 'mablmoov_bts2app_new',
    'username' => $_ENV['DB_USER'] ?? 'mablmoov_admin',
    'password' => $_ENV['DB_PASS'] ?? 'segMASZc8z3g_d5',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];