<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Services/DownloaderService.php';

$trackId = $_GET['id'] ?? '';
$isDownload = isset($_GET['dl']);

if (empty($trackId)) {
    http_response_code(400);
    exit('Missing track ID');
}

$downloader = new DownloaderService();
$url = $downloader->getDirectLink($trackId);

if (!$url) {
    http_response_code(404);
    exit('Track not found or Yandex API error');
}

// Если нажали "Скачать", принудительно скачиваем
if ($isDownload) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="yandex-track.mp3"');
} else {
    // Иначе отдаем браузеру для стриминга (плеер включится моментально)
    header('Content-Type: audio/mpeg');
}

// Мгновенный редирект на прямую ссылку
header('Location: ' . $url);
exit;