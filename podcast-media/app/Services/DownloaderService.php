<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use LuckyWins\YandexMusic\Client as YandexClient;

class DownloaderService
{
    private string $audioDir;
    private string $yandexToken;

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/app/config.local.php';
        $this->yandexToken = $config['yandex_token'] ?? '';
        $this->audioDir = dirname(__DIR__, 2) . '/storage/audio';
        
        if (!is_dir($this->audioDir)) {
            mkdir($this->audioDir, 0775, true);
        }
    }

    public function searchTracks(string $query, int $limit = 10): array
    {
        if (empty($this->yandexToken)) return [];
        try {
            $client = (new YandexClient($this->yandexToken))->init();
            $results = $client->search($query);
            
            if (empty($results->tracks->results)) return [];
            $rawTracks = array_slice($results->tracks->results, 0, $limit);
            
            $tracks = [];
            foreach ($rawTracks as $track) {
                $trackId = (string)($track->realId ?? $track->id ?? '');
                if (!$trackId) continue;

                $artist = $track->artists[0]->name ?? 'Unknown';
                $title = $track->title ?? 'Track';
                $safeName = preg_replace('/[\\\\\/:*?"<>|]/', '', "{$artist} - {$title}");
                $filename = trim($safeName) . '.mp3';
                
                $coverUri = $track->coverUri ?? $track->albums[0]->coverUri ?? null;
                $coverUrl = $coverUri ? 'https://' . str_replace('%%', '400x400', $coverUri) : null;

                $tracks[] = [
                    'id' => $trackId,
                    'title' => $title,
                    'artist' => $artist,
                    'duration' => (int) round(($track->durationMs ?? 0) / 1000),
                    'cover' => $coverUrl,
                    'filename' => $filename,
                    'isLocal' => is_file($this->audioDir . DIRECTORY_SEPARATOR . $filename)
                ];
            }
            return $tracks;
        } catch (Throwable $e) {
            error_log('Yandex search error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Возвращает прямую ссылку на MP3-файл (для мгновенного воспроизведения)
     */
    public function getDirectLink(string $trackId): string|false
    {
        if (empty($this->yandexToken)) return false;
        try {
            $client = (new YandexClient($this->yandexToken))->init();
            $variants = $client->tracksDownloadInfo($trackId);
            usort($variants, fn ($a, $b) => $b->bitrateInKbps <=> $a->bitrateInKbps);

            foreach ($variants as $variant) {
                if ($variant->codec === 'mp3') {
                    // Если ссылка прямая, отдаем её
                    if ($variant->direct) {
                        return $variant->downloadInfoUrl;
                    } 
                    
                    // Если ссылка в XML, вытаскиваем её оттуда (как делает сама библиотека)
                    $ch = curl_init($variant->downloadInfoUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $xmlStr = curl_exec($ch);
                    curl_close($ch);

                    if ($xmlStr) {
                        $xml = simplexml_load_string($xmlStr);
                        if ($xml && isset($xml->host, $xml->s, $xml->ts, $xml->path)) {
                            return "https://{$xml->host}/get-mp3/{$xml->s}/{$xml->ts}{$xml->path}";
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('getDirectLink error: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Скачивает файл на сервер (используется по кнопке "Скачать")
     */
    public function downloadTrack(string $trackId, string $filename): bool
    {
        if (empty($this->yandexToken)) return false;
        $filePath = $this->audioDir . DIRECTORY_SEPARATOR . $filename;
        if (is_file($filePath)) return true;

        try {
            $client = (new YandexClient($this->yandexToken))->init();
            $variants = $client->tracksDownloadInfo($trackId);
            usort($variants, fn ($a, $b) => $b->bitrateInKbps <=> $a->bitrateInKbps);

            foreach ($variants as $variant) {
                if ($variant->codec === 'mp3') {
                    $variant->download($filePath);
                    return true;
                }
            }
        } catch (Throwable $e) {
            error_log('Yandex download error: ' . $e->getMessage());
        }
        return false;
    }
}