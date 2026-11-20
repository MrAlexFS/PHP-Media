<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/bootstrap.php';

require_once dirname(__DIR__, 2)
    . '/app/Services/UploadService.php';

$pdo = appDb();

// Проверяем авторизацию и права доступа.
$user = requireAuthenticatedUser($pdo);
requireEditorOrAdmin($user);

// Контроллер принимает только POST-запросы.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');

    sendPlainError(
        405,
        'Метод запроса не поддерживается.'
    );
}

// Проверяем CSRF-токен.
$sessionToken = $_SESSION['csrf_token'] ?? null;
$postToken = $_POST['csrf_token'] ?? null;

if (
    !is_string($sessionToken)
    || !is_string($postToken)
    || $sessionToken === ''
    || !hash_equals($sessionToken, $postToken)
) {
    sendPlainError(
        403,
        'Ошибка проверки CSRF-токена.'
    );
}

// Получаем и проверяем метаданные.
$title = isset($_POST['title'])
    && is_string($_POST['title'])
    ? trim($_POST['title'])
    : '';

$description = isset($_POST['description'])
    && is_string($_POST['description'])
    ? trim($_POST['description'])
    : '';

$titleLength = function_exists('mb_strlen')
    ? mb_strlen($title, 'UTF-8')
    : strlen($title);

$descriptionLength = function_exists('mb_strlen')
    ? mb_strlen($description, 'UTF-8')
    : strlen($description);

if ($title === '' || $titleLength > 200) {
    sendPlainError(
        422,
        'Название обязательно и должно содержать не более 200 символов.'
    );
}

if ($descriptionLength > 10000) {
    sendPlainError(
        422,
        'Описание слишком длинное.'
    );
}

// Проверяем необязательную категорию.
$categoryId = null;
$categoryRaw = $_POST['category_id'] ?? '';

if ($categoryRaw !== '') {
    if (!is_string($categoryRaw)) {
        sendPlainError(422, 'Некорректная категория.');
    }

    $validatedCategoryId = filter_var(
        $categoryRaw,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($validatedCategoryId === false) {
        sendPlainError(422, 'Некорректная категория.');
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM categories WHERE id = :id'
    );

    $stmt->execute(['id' => $validatedCategoryId]);

    if (!$stmt->fetch()) {
        sendPlainError(422, 'Выбранная категория не существует.');
    }

    $categoryId = (int) $validatedCategoryId;
}

// Проверяем необязательную принадлежность к подкасту.
$podcastId = null;
$podcastRaw = $_POST['podcast_id'] ?? '';

if ($podcastRaw !== '') {
    if (!is_string($podcastRaw)) {
        sendPlainError(422, 'Некорректный идентификатор подкаста.');
    }

    $validatedPodcastId = filter_var(
        $podcastRaw,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($validatedPodcastId === false) {
        sendPlainError(422, 'Некорректный идентификатор подкаста.');
    }

    // Администратор может выбрать любой подкаст.
    // Редактор — только созданный им подкаст.
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare(
            'SELECT id FROM podcasts WHERE id = :id'
        );

        $stmt->execute(['id' => $validatedPodcastId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM podcasts
             WHERE id = :id
               AND created_by = :user_id'
        );

        $stmt->execute([
            'id' => $validatedPodcastId,
            'user_id' => $user['id'],
        ]);
    }

    if (!$stmt->fetch()) {
        sendPlainError(
            403,
            'Нет доступа к выбранному подкасту.'
        );
    }

    $podcastId = (int) $validatedPodcastId;
}

$audio = null;
$cover = null;

try {
    // Сначала проверяем и сохраняем файлы.
    $audio = storeAudioUpload($_FILES['audio'] ?? null);
    $cover = storeCoverUpload($_FILES['cover'] ?? null);

    // Затем сохраняем метаданные в БД.
    $stmt = $pdo->prepare(
        'INSERT INTO tracks (
            podcast_id,
            title,
            description,
            file_path,
            cover_path,
            mime_type,
            file_size_bytes,
            category_id,
            uploader_id,
            status
        ) VALUES (
            :podcast_id,
            :title,
            :description,
            :file_path,
            :cover_path,
            :mime_type,
            :file_size_bytes,
            :category_id,
            :uploader_id,
            :status
        )'
    );

    $stmt->execute([
        'podcast_id' => $podcastId,
        'title' => $title,
        'description' => $description !== ''
            ? $description
            : null,
        'file_path' => $audio['relative_path'],
        'cover_path' => $cover['relative_path'] ?? null,
        'mime_type' => $audio['mime_type'],
        'file_size_bytes' => $audio['size_bytes'],
        'category_id' => $categoryId,
        'uploader_id' => $user['id'],
        'status' => 'draft',
    ]);

    $trackId = (int) $pdo->lastInsertId();

} catch (UploadValidationException $e) {
    // Не оставляем аудио без записи в БД.
    removeStoredUpload($audio);
    removeStoredUpload($cover);

    sendPlainError(422, $e->getMessage());

} catch (Throwable $e) {
    // Внутренние сведения об ошибке не показываем посетителю.
    error_log('Ошибка загрузки аудио: ' . $e->getMessage());

    removeStoredUpload($audio);
    removeStoredUpload($cover);

    sendPlainError(
        500,
        'Не удалось сохранить аудиоматериал.'
    );
}

// Материал создан, но пока не опубликован.
http_response_code(201);
header('Content-Type: text/plain; charset=utf-8');

echo 'Аудиоматериал сохранён. ID: '
    . $trackId
    . '. Статус: draft.';