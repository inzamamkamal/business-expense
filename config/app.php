<?php

return [
    'app_name' => 'BTS DISC 2.0 Application',
    'app_version' => '2.0.0',
    'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => 'Asia/Kolkata',
    'session_timeout' => 7200, // 2 hours
    'csrf_protection' => true,
    'debug' => $_ENV['DEBUG'] ?? false,
    'upload_path' => __DIR__ . '/../public/uploads/',
    'max_upload_size' => '10M',
    'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
];