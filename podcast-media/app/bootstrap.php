<?php
declare(strict_types=1);

// Корень проекта: каталог, содержащий app и public_html.
define('PROJECT_ROOT', dirname(__DIR__));

/**
 * Загружает закрытую конфигурацию.
 */
function appConfig(): array
{
    static $config = null;

    if ($config === null) {
        $configFile = __DIR__ . '/config.local.php';

        if (!is_file($configFile)) {
            throw new RuntimeException(
                'Не найден файл конфигурации приложения.'
            );
        }

        $config = require $configFile;
    }

    return $config;
}

$config = appConfig();

// Абсолютные пути к закрытому файловому хранилищу.
define(
    'STORAGE_ROOT',
    rtrim(
        (string) $config['storage_root'],
        DIRECTORY_SEPARATOR
    )
);

define(
    'AUDIO_STORAGE_DIR',
    STORAGE_ROOT . DIRECTORY_SEPARATOR . 'audio'
);

define(
    'COVER_STORAGE_DIR',
    STORAGE_ROOT . DIRECTORY_SEPARATOR . 'covers'
);

// Настройки сессии.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PODCASTSESSID');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (
            isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
        ),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Возвращает подключение PDO.
 * Подключение создаётся только один раз за запрос.
 */
function appDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = appConfig()['db'];

    // При необходимости параметры можно переопределить
    // переменными окружения сервера.
    $dsn = getenv('DB_DSN')
        ?: ($config['dsn'] ?? '');

    $username = getenv('DB_USER')
        ?: ($config['username'] ?? '');

    $envPassword = getenv('DB_PASS');

    $password = $envPassword !== false
        ? $envPassword
        : ($config['password'] ?? '');

    if ($dsn === '' || $username === '') {
        throw new RuntimeException(
            'Не настроены параметры подключения к БД.'
        );
    }

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

/**
 * Возвращает текущего активного пользователя.
 * Роль проверяется по БД, а не только по данным сессии.
 */
function requireAuthenticatedUser(PDO $pdo): array
{
    $userId = filter_var(
        $_SESSION['user_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($userId === false) {
        sendPlainError(401, 'Необходима авторизация.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, role, is_active
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);

        sendPlainError(
            401,
            'Учётная запись неактивна или не найдена.'
        );
    }

    return $user;
}

/**
 * Допускает только редактора и администратора.
 */
function requireEditorOrAdmin(array $user): void
{
    if (!in_array(
        $user['role'],
        ['editor', 'admin'],
        true
    )) {
        sendPlainError(
            403,
            'Недостаточно прав для выполнения операции.'
        );
    }
}

/**
 * Завершает запрос с безопасным текстовым сообщением.
 */
function sendPlainError(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');

    exit($message);
}