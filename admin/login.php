<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';

if (current_user()) {
    redirect('index.php');
}

// Если администратора ещё нет — первый вход создаёт его.
$hasAdmin = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!$hasAdmin) {
        $password2 = (string) ($_POST['password2'] ?? '');
        if ($username === '' || strlen($password) < 6) {
            $error = 'Укажите логин и пароль не короче 6 символов.';
        } elseif ($password !== $password2) {
            $error = 'Пароли не совпадают.';
        } else {
            db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['admin'] = $username;
            flash('Администратор создан. Добро пожаловать!');
            redirect('index.php');
        }
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = $user['username'];
            redirect('index.php');
        }
        usleep(500000); // небольшая пауза против перебора паролей
        $error = 'Неверный логин или пароль.';
    }
}

page_header($hasAdmin ? 'Вход' : 'Создание администратора', '../', true);
?>
<div class="auth-box panel">
    <h1><?= $hasAdmin ? 'Вход в админку' : 'Создайте администратора' ?></h1>
    <?php if (!$hasAdmin): ?>
        <p class="hint">Это первый запуск. Придумайте логин и пароль — они понадобятся для входа.</p>
    <?php endif; ?>
    <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Логин</label>
            <input type="text" id="username" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required>
        </div>
        <?php if (!$hasAdmin): ?>
        <div class="field">
            <label for="password2">Повторите пароль</label>
            <input type="password" id="password2" name="password2" required>
        </div>
        <?php endif; ?>
        <button class="btn" type="submit"><?= $hasAdmin ? 'Войти' : 'Создать и войти' ?></button>
    </form>
</div>
<?php page_footer();
