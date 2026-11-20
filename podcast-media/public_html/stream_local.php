<?php
declare(strict_types=1);

$file = basename((string) ($_GET['file'] ?? ''));
$demoFreq = (float) ($_GET['demo'] ?? 0);
$isDownload = isset($_GET['dl']);

// 1. Если запрошен реальный файл из storage/audio/
if ($file !== '') {
    $path = dirname(__DIR__) . '/storage/audio/' . $file;
    if (!is_file($path)) {
        http_response_code(404);
        exit('Файл не найден');
    }

    $mime = mime_content_type($path) ?: 'audio/mpeg';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Accept-Ranges: bytes');

    if ($isDownload) {
        header('Content-Disposition: attachment; filename="' . $file . '"');
    }

    readfile($path);
    exit;
}

// 2. Если запрошен встроенный демо-трек (генерируем мелодичный WAV-файл 15 сек без интернета)
$freq = $demoFreq > 50 ? $demoFreq : 261.63;
$sampleRate = 22050;
$duration = 15;
$numSamples = $sampleRate * $duration;
$dataSize = $numSamples * 2;

$name = preg_replace('/[^a-zA-Zа-яА-ЯёЁ0-9_\-\s]/u', '', (string) ($_GET['name'] ?? 'demo_track'));
$name = trim($name) !== '' ? trim($name) : 'demo_track';

header('Content-Type: audio/wav');
header('Content-Length: ' . (44 + $dataSize));
if ($isDownload) {
    header('Content-Disposition: attachment; filename="' . $name . '.wav"');
}

// Заголовок WAV
echo pack('a4Va4a4VvvVVvva4V',
    'RIFF', 36 + $dataSize, 'WAVE',
    'fmt ', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16,
    'data', $dataSize
);

// Генерируем мягкий аккорд с перебором нот
$notes = [$freq, $freq * 1.25, $freq * 1.5, $freq * 1.25];
$samplesPerBeat = (int) ($sampleRate * 0.75);
$buffer = '';

for ($i = 0; $i < $numSamples; $i++) {
    $noteIndex = (int) ($i / $samplesPerBeat) % count($notes);
    $currentFreq = $notes[$noteIndex];
    $posInBeat = ($i % $samplesPerBeat) / $samplesPerBeat;
    $envelope = sin(M_PI * $posInBeat); // Плавное затухание каждой ноты
    $sample = (int) (12000 * $envelope * sin(2 * M_PI * $currentFreq * $i / $sampleRate));
    $buffer .= pack('v', $sample & 0xFFFF);

    if (strlen($buffer) >= 8192) {
        echo $buffer;
        $buffer = '';
    }
}
if ($buffer !== '') {
    echo $buffer;
}
