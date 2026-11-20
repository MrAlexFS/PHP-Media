<?php
declare(strict_types=1);

return [
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=podcast_media;charset=utf8mb4',
        'username' => 'podcast_app',
        'password' => '39135W00844424as',
    ],

    'storage_root' => dirname(__DIR__) . '/storage',

    // ВСТАВЬТЕ СЮДА ВАШ CLIENT ID ОТ JAMENDO:
    'yandex_token' => 'y0__wgBENC_vaUHGN74BiDxvIHqGTDr866LCB4Fw4nCZfEt0HGU-9b2G4VGhEgK',
];
