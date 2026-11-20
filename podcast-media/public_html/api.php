<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/app/Services/DownloaderService.php';

$query = trim((string) ($_GET['q'] ?? ''));
$tag = trim((string) ($_GET['tag'] ?? ''));
$isWave = isset($_GET['wave']);
$action = $_GET['action'] ?? 'search';

$audioDir = dirname(__DIR__) . '/storage/audio';
if (!is_dir($audioDir)) mkdir($audioDir, 0775, true);

function makeLocalCover(string $title, int $seed = 0): string {
    $letter = mb_strtoupper(mb_substr(trim($title), 0, 1)) ?: '♫';
    $colors = ['#293621', '#212c36', '#332136', '#362e21', '#213631'];
    $bgColor = $colors[$seed % count($colors)];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="500" height="280" viewBox="0 0 500 280">'
         . '<rect width="500" height="280" fill="' . $bgColor . '"/>'
         . '<circle cx="250" cy="140" r="64" fill="#b6f36a" fill-opacity="0.12"/>'
         . '<text x="250" y="162" font-family="Arial" font-size="68" font-weight="bold" fill="#b6f36a" text-anchor="middle">'
         . htmlspecialchars($letter, ENT_XML1) . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

$downloader = new DownloaderService();

// Сохранение в медиатеку (для кнопки "Сохранить")
if ($action === 'save_to_library') {
    $trackId = $_GET['id'] ?? '';
    $filename = $_GET['filename'] ?? '';
    if ($downloader->downloadTrack($trackId, $filename)) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка сохранения']);
    }
    exit;
}

if ($isWave) {
    // Если передан жанр (q), используем его, иначе берем рандом
    if ($query === '') {
        $waves = ['популярное 2024', 'русский рэп', 'лучшие хиты', 'электронная музыка'];
        $query = $waves[array_rand($waves)];
    }
}

$allTracks = [];

// 1. Локальные файлы
$files = glob($audioDir . '/*.{mp3,ogg,wav,m4a}', GLOB_BRACE) ?: [];
foreach ($files as $idx => $filePath) {
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);

    if (str_contains($fileName, ' - ')) {
        [$author, $title] = explode(' - ', $fileName, 2);
    } else {
        $author = 'Моя медиатека';
        $title = $fileName;
    }
    $streamUrl = 'stream_local.php?file=' . urlencode(basename($filePath));

    $allTracks[] = [
        'id'          => 'local_' . md5($filePath),
        'title'       => trim($title),
        'author'      => trim($author),
        'duration'    => 180,
        'cover'       => makeLocalCover($title, $idx),
        'audio'       => $streamUrl,
        'download'    => $streamUrl . '&dl=1',
        'canDownload' => true,
        'isLocal'     => true,
        'tags'        => ['local', strtolower($ext)],
    ];
}

// 2. Результаты из Яндекса
if ($query !== '' || $isWave) {
    $yandexTracks = $downloader->searchTracks($query, 10);
    foreach ($yandexTracks as $yt) {
        if ($yt['isLocal']) continue;

        $allTracks[] = [
            'id'          => 'ya_' . $yt['id'],
            'title'       => $yt['title'],
            'author'      => $yt['artist'],
            'duration'    => $yt['duration'],
            'cover'       => $yt['cover'] ?: makeLocalCover($yt['title']),
            'audio'       => '', // Ссылка будет сформирована в player.js
            'download'    => 'stream_remote.php?id=' . urlencode($yt['id']) . '&dl=1',
            'canDownload' => true,
            'isLocal'     => false,
            'ya_id'       => $yt['id'],
            'filename'    => $yt['filename'],
            'tags'        => ['yandex', 'online'],
        ];
    }
}

if ($tag !== '') {
    $allTracks = array_values(array_filter($allTracks, fn($t) => in_array(mb_strtolower($tag), $t['tags'] ?? [], true)));
}

echo json_encode(['tracks' => $allTracks], JSON_UNESCAPED_UNICODE);