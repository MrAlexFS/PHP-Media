<?php
declare(strict_types=1);

require_once dirname(__DIR__)
    . '/app/bootstrap.php';

$pdo = appDb();
$user = requireAuthenticatedUser($pdo);

$trackId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($trackId === false || $trackId === null) {
    sendPlainError(400, 'Некорректный ID материала.');
}

$stmt = $pdo->prepare(
    'SELECT cover_path, status, uploader_id
     FROM tracks
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute(['id' => $trackId]);
$track = $stmt->fetch();

if (!$track || empty($track['cover_path'])) {
    sendPlainError(404, 'Обложка не найдена.');
}

$isAllowed = (
    $track['status'] === 'published'
    || $user['role'] === 'admin'
    || (
        $user['role'] === 'editor'
        && $track['uploader_id'] !== null
        && (int) $track['uploader_id'] === (int) $user['id']
    )
);

if (!$isAllowed) {
    sendPlainError(404, 'Обложка не найдена.');
}

if (!preg_match(
    '~\Acovers/([a-f0-9]{32}\.(?:jpg|jpeg|png|webp))\z~',
    (string) $track['cover_path'],
    $matches
)) {
    sendPlainError(404, 'Обложка не найдена.');
}

$baseDirectory = realpath(COVER_STORAGE_DIR);

if ($baseDirectory === false) {
    sendPlainError(404, 'Обложка не найдена.');
}

$path = realpath(
    $baseDirectory . DIRECTORY_SEPARATOR . $matches[1]
);

if (
    $path === false
    || !str_starts_with(
        $path,
        $baseDirectory . DIRECTORY_SEPARATOR
    )
    || !is_file($path)
    || !is_readable($path)
) {
    sendPlainError(404, 'Обложка не найдена.');
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

$mimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

if (!isset($mimeMap[$extension])) {
    sendPlainError(404, 'Обложка не найдена.');
}

header('Content-Type: ' . $mimeMap[$extension]);
header('Content-Disposition: inline; filename="cover-'
    . (int) $trackId . '.' . $extension . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

header('Content-Length: ' . filesize($path));

readfile($path);
exit;