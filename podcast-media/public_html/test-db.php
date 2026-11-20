<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/app/config.local.php';

try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['username'],
        $config['db']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $result = $pdo->query('SELECT DATABASE() AS db_name')->fetch();

    echo 'Подключение успешно!<br>';
    echo 'База данных: ' .
        htmlspecialchars($result['db_name'], ENT_QUOTES, 'UTF-8');

} catch (PDOException $e) {
    http_response_code(500);

    echo '<pre>';
    echo htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );
    echo '</pre>';
}