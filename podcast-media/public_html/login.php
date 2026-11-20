<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$pdo = appDb();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: /admin/index.php');
    exit;
}

if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['login_csrf_token'], $token)
    ) {
        $error = 'Страница устарела. Обнови её и попробуй снова.';
    } elseif ($login === '' || $password === '') {
        $error = 'Заполни логин и пароль.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, login, password_hash, display_name, role, is_active
             FROM users
             WHERE login = :login
             LIMIT 1'
        );

        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $user &&
            (int) $user['is_active'] === 1 &&
            password_verify($password, $user['password_hash'])
        ) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'login' => $user['login'],
                'display_name' => $user['display_name'],
                'role' => $user['role'],
            ];

            unset($_SESSION['login_csrf_token']);

            header('Location: /admin/index.php');
            exit;
        }

        $error = 'Неверный логин или пароль.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Медиатека подкастов</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            color: #202124;
            background: #f3f5f9;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 32px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, .08);
        }

        h1 { margin: 0 0 8px; font-size: 26px; }

        .subtitle {
            margin: 0 0 24px;
            color: #666;
            line-height: 1.5;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d5d9e0;
            border-radius: 8px;
            font-size: 16px;
        }

        input:focus {
            outline: 2px solid #9bbcff;
            border-color: #356fe0;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px;
            border: 0;
            border-radius: 8px;
            background: #245fe5;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover { background: #194fc9; }

        .error {
            margin-bottom: 16px;
            padding: 12px;
            border-radius: 8px;
            background: #fff0f0;
            color: #a32121;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <main class="login-card">
        <h1>С возвращением</h1>
        <p class="subtitle">Войди в медиатеку подкастов, чтобы управлять контентом.</p>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/login.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['login_csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <label for="login">Логин</label>
            <input
                id="login"
                name="login"
                type="text"
                autocomplete="username"
                maxlength="100"
                required
            >

            <label for="password">Пароль</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Войти</button>
        </form>
    </main>
</body>
</html>
