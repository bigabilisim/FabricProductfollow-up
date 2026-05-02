<?php

return [
    'app' => [
        'installed' => false,
        'site_name' => 'Fabrika QR Bakım Takip',
        'version' => '1.0V',
        'base_url' => 'http://qr-bakim.test',
        'base_path' => '',
        'timezone' => 'Europe/Istanbul',
        'maintenance_warning_days' => [30, 14, 7, 3, 1],
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'factory_qr',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'driver' => 'smtp',
        'from_email' => 'bigaofis@alarmbigabilisim.com',
        'from_name' => 'Fabrika QR Bakım Takip',
        'smtp_host' => 'smtp.yandex.com.tr',
        'smtp_port' => 465,
        'smtp_user' => 'bigaofis@alarmbigabilisim.com',
        'smtp_password' => '',
        'smtp_encryption' => 'ssl',
        'backup_recipients' => ['bigaofis@alarmbigabilisim.com'],
    ],
    'telegram' => [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
    ],
    'whatsapp' => [
        'enabled' => false,
        'webhook_url' => '',
        'token' => '',
    ],
];
