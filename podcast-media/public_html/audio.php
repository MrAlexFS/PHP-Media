<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/app/bootstrap.php';

$pdo = appDb();

// В этой реализации слушать аудио могут авторизованные пользователи.
$user = requireAuthenticatedUser($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');

    sendPlainError(405, 'Метод запроса не поддерживается.');
}

// Проверяем идентификатор материала.
$trackId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($trackId === false || $trackId === null) {
    sendPlainError(400, 'Некорректный ID аудиоматериала.');
}

// Получаем запись из БД, а не путь от пользователя.
$stmt = $pdo->prepare(
    'SELECT id, file_path, status, uploader_id
     FROM tracks
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute(['id' => $trackId]);
$track = $stmt->fetch();

if (!$track) {
    sendPlainError(404, 'Аудиоматериал не найден.');
}

// Определяем право на прослушивание.
$isAdmin = $user['role'] === 'admin';

$isOwnerEditor = (
    $user['role'] === 'editor'
    && $track['uploader_id'] !== null
    && (int) $track['uploader_id'] === (int) $user['id']
);

$isPublished = $track['status'] === 'published';

if (!$isPublished && !$isAdmin && !$isOwnerEditor) {
    // Не раскрываем существование закрытого материала.
    sendPlainError(404, 'Аудиоматериал не найден.');
}

// Путь должен соответствовать шаблону,
// который создаёт наш UploadService.
$relativePath = (string) $track['file_path'];

if (!preg_match(
    '~\Aaudio/([a-f0-9]{32}\.(?:mp3|wav|ogg))\z~',
    $relativePath,
    $matches
)) {
    error_log('Недопустимый путь аудиоматериала ID ' . $trackId);

    sendPlainError(404, 'Файл не найден.');
}

// Разрешаем путь только внутри каталога аудио.
$baseDirectory = realpath(AUDIO_STORAGE_DIR);

if ($baseDirectory === false) {
    sendPlainError(404, 'Файл не найден.');
}

$filePath = realpath(
    $baseDirectory
    . DIRECTORY_SEPARATOR
    . $matches[1]
);

if (
    $filePath === false
    || !str_starts_with(
        $filePath,
        $baseDirectory . DIRECTORY_SEPARATOR
    )
    || !is_file($filePath)
    || !is_readable($filePath)
) {
    sendPlainError(404, 'Файл не найден.');
}

$extension = strtolower(
    pathinfo($filePath, PATHINFO_EXTENSION)
);

// MIME-тип определяется разрешённым расширением,
// созданным нашим сервисом загрузки.
$mimeMap = [
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
];

if (!isset($mimeMap[$extension])) {
    sendPlainError(404, 'Файл не найден.');
}

$mimeType = $mimeMap[$extension];

$fileSize = filesize($filePath);

if ($fileSize === false || $fileSize < 1) {
    sendPlainError(404, 'Файл не найден.');
}

// Открываем файл до отправки заголовков.
$handle = fopen($filePath, 'rb');

if ($handle === false) {
    sendPlainError(500, 'Не удалось открыть аудиофайл.');
}

$start = 0;
$end = $fileSize - 1;
$statusCode = 200;
$range = null;

// Обрабатываем одиночный HTTP Range.
// Поддерживаются обычный диапазон, открытый конец
// и суффиксный диапазон последних байтов.
$rangeHeader = trim($_SERVER['HTTP_RANGE'] ?? '');

if ($rangeHeader !== '') {
    $validRange = (
        !str_contains($rangeHeader, ',')
        && preg_match(
            '/\Abytes=(\d*)-(\d*)\z/',
            $rangeHeader,
            $range
        ) === 1
        && ($range[1] !== '' || $range[2] !== '')
    );

    if (!$validRange) {
        fclose($handle);

        header('Content-Range: bytes */' . $fileSize);

        sendPlainError(
            416,
            'Запрошенный диапазон байтов не поддерживается.'
        );
    }

    if ($range[1] === '') {
        // Пример: Range: bytes=-500
        $suffixLength = (int) $range[2];

        if ($suffixLength <= 0) {
            fclose($handle);

            header('Content-Range: bytes */' . $fileSize);

            sendPlainError(416, 'Недопустимый диапазон байтов.');
        }

        $start = max(0, $fileSize - $suffixLength);
        $end = $fileSize - 1;

    } else {
        $start = (int) $range[1];

        $end = $range[2] === ''
            ? $fileSize - 1
            : (int) $range[2];

        if ($start >= $fileSize || $start > $end) {
            fclose($handle);

            header('Content-Range: bytes */' . $fileSize);

            sendPlainError(416, 'Недопустимый диапазон байтов.');
        }

        $end = min($end, $fileSize - 1);
    }

    $statusCode = 206;
}

$length = $end - $start + 1;

if (fseek($handle, $start) !== 0) {
    fclose($handle);

    sendPlainError(500, 'Ошибка чтения аудиофайла.');
}

// Убираем буферизацию перед потоковой передачей.
while (ob_get_level() > 0) {
    ob_end_clean();
}

http_response_code($statusCode);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="track-'
    . (int) $trackId . '.' . $extension . '"');

header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');

// Не позволяем совместно использовать приватный ответ
// через общий кэш промежуточного прокси.
header('Cache-Control: private, no-store');

header('Content-Length: ' . $length);

if ($statusCode === 206) {
    header(
        'Content-Range: bytes '
        . $start . '-' . $end . '/' . $fileSize
    );
}

// HEAD возвращает заголовки, но не тело файла.
if ($method === 'HEAD') {
    fclose($handle);
    exit;
}

// Передаём файл частями, не загружая его целиком в RAM.
$remaining = $length;
$chunkSize = 8192;

set_time_limit(0);

while ($remaining > 0 && !feof($handle)) {
    $chunk = fread(
        $handle,
        min($chunkSize, $remaining)
    );

    if ($chunk === false || $chunk === '') {
        break;
    }

    echo $chunk;

    $remaining -= strlen($chunk);

    flush();

    if (connection_aborted()) {
        break;
    }
}

fclose($handle);
exit;