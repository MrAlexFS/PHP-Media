<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/bootstrap.php';

$pdo = appDb();
$user = requireAuthenticatedUser($pdo);
requireEditorOrAdmin($user);

// Создаём CSRF-токен, если его ещё нет.
if (
    !isset($_SESSION['csrf_token'])
    || !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Загрузка аудиоматериала</title>
</head>
<body>

<h1>Загрузка аудиоматериала</h1>

<form
    action="/admin/upload-track.php"
    method="post"
    enctype="multipart/form-data"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            $_SESSION['csrf_token'],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>"
    >

    <label for="title">Название</label>
    <input
        id="title"
        type="text"
        name="title"
        maxlength="200"
        required
    >

    <br><br>

    <label for="description">Описание</label>
    <textarea
        id="description"
        name="description"
        maxlength="10000"
    ></textarea>

    <br><br>

    <label for="category_id">ID категории</label>
    <input
        id="category_id"
        type="number"
        name="category_id"
        min="1"
    >

    <br><br>

    <label for="podcast_id">ID подкаста (необязательно)</label>
    <input
        id="podcast_id"
        type="number"
        name="podcast_id"
        min="1"
    >

    <br><br>

    <label for="audio">Аудиофайл</label>
    <input
        id="audio"
        type="file"
        name="audio"
        accept=".mp3,.wav,.ogg"
        required
    >

    <p>Допустимые форматы: MP3, WAV, OGG. До 50 МиБ.</p>

    <label for="cover">Обложка (необязательно)</label>
    <input
        id="cover"
        type="file"
        name="cover"
        accept=".jpg,.jpeg,.png,.webp"
    >

    <p>Допустимые форматы: JPEG, PNG, WebP. До 5 МиБ.</p>

    <button type="submit">Загрузить</button>
</form>

</body>
</html>