<?php
declare(strict_types=1);

/**
 * Исключение для ошибок проверки загружаемых файлов.
 */
final class UploadValidationException extends RuntimeException
{
}

/**
 * Проверяет состояние загруженного файла и его размер.
 */
function validateIncomingUpload(
    array $file,
    int $maxBytes,
    string $label
): array {
    if (
        !isset($file['error'], $file['tmp_name'], $file['name'])
        || !is_string($file['tmp_name'])
        || !is_string($file['name'])
    ) {
        throw new UploadValidationException(
            'Некорректные данные загружаемого файла.'
        );
    }

    $errorCode = (int) $file['error'];

    if ($errorCode !== UPLOAD_ERR_OK) {
        $message = match ($errorCode) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE =>
                "Файл {$label} превышает допустимый размер.",

            UPLOAD_ERR_PARTIAL =>
                "Файл {$label} загружен не полностью.",

            UPLOAD_ERR_NO_TMP_DIR =>
                'На сервере отсутствует временная директория.',

            UPLOAD_ERR_CANT_WRITE =>
                'Не удалось записать файл на сервере.',

            default =>
                "Ошибка загрузки файла {$label}.",
        };

        throw new UploadValidationException($message);
    }

    // Не принимаем произвольный путь к локальному файлу.
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new UploadValidationException(
            'Файл не прошёл проверку источника загрузки.'
        );
    }

    $actualSize = filesize($file['tmp_name']);

    if (
        $actualSize === false
        || $actualSize < 1
        || $actualSize > $maxBytes
        || (int) ($file['size'] ?? 0) > $maxBytes
    ) {
        throw new UploadValidationException(
            "Недопустимый размер файла {$label}."
        );
    }

    return [
        'tmp_name' => $file['tmp_name'],
        'original_name' => $file['name'],
        'size_bytes' => (int) $actualSize,
    ];
}

/**
 * Создаёт закрытый каталог, если он ещё не существует.
 */
function ensurePrivateDirectory(string $directory): void
{
    if (
        !is_dir($directory)
        && !mkdir($directory, 0750, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'Не удалось создать каталог файлового хранилища.'
        );
    }

    if (!is_writable($directory)) {
        throw new RuntimeException(
            'У веб-сервера нет прав на запись в хранилище.'
        );
    }
}

/**
 * Проверяет MIME-тип файла.
 */
function detectMimeType(string $tmpName): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);

    if (!is_string($mime) || $mime === '') {
        throw new UploadValidationException(
            'Не удалось определить MIME-тип файла.'
        );
    }

    return $mime;
}

/**
 * Сохраняет аудио в приватном каталоге.
 *
 * Разрешённые расширения: mp3, wav, ogg.
 * Максимальный размер: 50 МиБ.
 */
function storeAudioUpload(?array $file): array
{
    if ($file === null) {
        throw new UploadValidationException(
            'Необходимо выбрать аудиофайл.'
        );
    }

    $upload = validateIncomingUpload(
        $file,
        50 * 1024 * 1024,
        'аудио'
    );

    $extension = strtolower(
        pathinfo(
            $upload['original_name'],
            PATHINFO_EXTENSION
        )
    );

    // Расширение сопоставляется с допустимыми MIME-типами.
    $allowed = [
        'mp3' => [
            'audio/mpeg',
            'audio/mp3',
            'audio/x-mpeg',
        ],

        'wav' => [
            'audio/wav',
            'audio/x-wav',
            'audio/wave',
            'audio/vnd.wave',
        ],

        'ogg' => [
            'audio/ogg',
            'application/ogg',
        ],
    ];

    if (!isset($allowed[$extension])) {
        throw new UploadValidationException(
            'Разрешены только файлы MP3, WAV и OGG.'
        );
    }

    $detectedMime = detectMimeType($upload['tmp_name']);

    if (!in_array(
        $detectedMime,
        $allowed[$extension],
        true
    )) {
        throw new UploadValidationException(
            'Расширение файла не соответствует его MIME-типу.'
        );
    }

    ensurePrivateDirectory(AUDIO_STORAGE_DIR);

    // Исходное имя пользователя не используется в пути.
    $filename = bin2hex(random_bytes(16))
        . '.'
        . $extension;

    $target = AUDIO_STORAGE_DIR
        . DIRECTORY_SEPARATOR
        . $filename;

    if (!move_uploaded_file(
        $upload['tmp_name'],
        $target
    )) {
        throw new RuntimeException(
            'Не удалось сохранить аудиофайл.'
        );
    }

    // Дополнительное ограничение доступа к файлу на Linux.
    @chmod($target, 0640);

    // Относительный путь записывается в MySQL.
    return [
        'relative_path' => 'audio/' . $filename,
        'absolute_path' => $target,
        'mime_type' => match ($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
        },
        'size_bytes' => $upload['size_bytes'],
    ];
}

/**
 * Сохраняет обложку.
 *
 * Разрешены JPEG, PNG и WebP.
 * SVG намеренно не разрешён.
 */
function storeCoverUpload(?array $file): ?array
{
    // Обложка необязательна.
    if (
        $file === null
        || (int) ($file['error'] ?? -1) === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $upload = validateIncomingUpload(
        $file,
        5 * 1024 * 1024,
        'обложки'
    );

    $extension = strtolower(
        pathinfo(
            $upload['original_name'],
            PATHINFO_EXTENSION
        )
    );

    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    if (!isset($allowed[$extension])) {
        throw new UploadValidationException(
            'Обложка должна иметь формат JPEG, PNG или WebP.'
        );
    }

    $detectedMime = detectMimeType($upload['tmp_name']);
    $imageInfo = @getimagesize($upload['tmp_name']);

    if (
        $detectedMime !== $allowed[$extension]
        || $imageInfo === false
        || ($imageInfo['mime'] ?? '') !== $allowed[$extension]
    ) {
        throw new UploadValidationException(
            'Файл обложки не прошёл проверку изображения.'
        );
    }

    // Ограничиваем размеры изображения и число пикселей.
    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];

    if (
        $width < 1
        || $height < 1
        || $width > 8000
        || $height > 8000
        || $width * $height > 25000000
    ) {
        throw new UploadValidationException(
            'Недопустимые размеры изображения.'
        );
    }

    ensurePrivateDirectory(COVER_STORAGE_DIR);

    $filename = bin2hex(random_bytes(16))
        . '.'
        . $extension;

    $target = COVER_STORAGE_DIR
        . DIRECTORY_SEPARATOR
        . $filename;

    if (!move_uploaded_file(
        $upload['tmp_name'],
        $target
    )) {
        throw new RuntimeException(
            'Не удалось сохранить обложку.'
        );
    }

    @chmod($target, 0640);

    return [
        'relative_path' => 'covers/' . $filename,
        'absolute_path' => $target,
        'mime_type' => $allowed[$extension],
        'size_bytes' => $upload['size_bytes'],
    ];
}

/**
 * Удаляет уже сохранённый файл при ошибке последующей операции.
 */
function removeStoredUpload(?array $file): void
{
    if (
        $file !== null
        && isset($file['absolute_path'])
        && is_file($file['absolute_path'])
    ) {
        if (!@unlink($file['absolute_path'])) {
            error_log(
                'Не удалось удалить временный файл загрузки.'
            );
        }
    }
}