<?php
// Общая инициализация: сессия, база данных, вспомогательные функции.

declare(strict_types=1);

const SITE_NAME = 'Книга рецептов';
const DATA_DIR = __DIR__ . '/../data';
const UPLOAD_DIR = __DIR__ . '/../uploads';
const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5 МБ

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

if (!extension_loaded('pdo_sqlite')) {
    http_response_code(500);
    exit('Не включено расширение pdo_sqlite. Запустите сайт через start.bat или включите extension=pdo_sqlite в php.ini.');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }
    $file = DATA_DIR . '/recipes.sqlite';
    $isNew = !file_exists($file);

    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS recipes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
        description TEXT NOT NULL DEFAULT \'\',
        ingredients TEXT NOT NULL DEFAULT \'\',
        steps TEXT NOT NULL DEFAULT \'\',
        cook_time INTEGER NOT NULL DEFAULT 0,
        servings INTEGER NOT NULL DEFAULT 0,
        image TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    migrate_categories($pdo);

    if ($isNew) {
        seed_recipes($pdo);
    }
    return $pdo;
}

// Старые базы хранили категорию текстом в recipes.category — переносим в таблицу categories.
function migrate_categories(PDO $pdo): void
{
    $cols = $pdo->query('PRAGMA table_info(recipes)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array('category_id', $cols, true)) {
        return;
    }
    $pdo->beginTransaction();
    $pdo->exec('ALTER TABLE recipes ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL');
    $pdo->exec("INSERT OR IGNORE INTO categories (name) SELECT DISTINCT trim(category) FROM recipes WHERE trim(category) <> ''");
    $pdo->exec('UPDATE recipes SET category_id = (SELECT id FROM categories WHERE name = trim(recipes.category))');
    $pdo->commit();
}

// Все категории с количеством рецептов в каждой.
function all_categories(): array
{
    return db()->query('SELECT c.id, c.name, COUNT(r.id) AS cnt
        FROM categories c LEFT JOIN recipes r ON r.category_id = c.id
        GROUP BY c.id ORDER BY c.name')->fetchAll();
}

// Возвращает id категории с таким названием, создавая её при необходимости.
function category_id_by_name(string $name): int
{
    $stmt = db()->prepare('SELECT id FROM categories WHERE name = ?');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    db()->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
    return (int) db()->lastInsertId();
}

// Пара примеров, чтобы сайт не был пустым при первом запуске.
function seed_recipes(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT INTO recipes (title, category_id, description, ingredients, steps, cook_time, servings)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        'Блины на молоке',
        category_id_by_name('Завтраки'),
        'Тонкие классические блины — подойдут и со сметаной, и с вареньем.',
        "Молоко — 500 мл\nЯйца — 2 шт.\nМука — 200 г\nСахар — 1 ст. л.\nСоль — щепотка\nРастительное масло — 2 ст. л.",
        "Взбейте яйца с сахаром и солью.\nВлейте половину молока и всыпьте муку, перемешайте до гладкости.\nДобавьте оставшееся молоко и масло, дайте тесту постоять 15 минут.\nЖарьте на разогретой сковороде по 1 минуте с каждой стороны.",
        40,
        4,
    ]);
    $stmt->execute([
        'Борщ',
        category_id_by_name('Супы'),
        'Наваристый борщ на говяжьем бульоне.',
        "Говядина на кости — 500 г\nСвёкла — 2 шт.\nКапуста — 300 г\nКартофель — 3 шт.\nМорковь — 1 шт.\nЛук — 1 шт.\nТоматная паста — 2 ст. л.\nЧеснок, соль, лавровый лист",
        "Сварите бульон из говядины (около 1,5 часа).\nОбжарьте лук, морковь и свёклу с томатной пастой.\nДобавьте в бульон картофель, через 10 минут — капусту.\nВыложите зажарку, посолите, добавьте лавровый лист и чеснок.\nВарите ещё 10 минут и дайте настояться.",
        120,
        6,
    ]);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function lines(string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), 'strlen'));
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $msg;
}

// --- Защита форм от подделки запросов (CSRF) ---

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Неверный токен формы. Обновите страницу и попробуйте снова.');
    }
}

// --- Авторизация ---

function current_user(): ?string
{
    static $checked = false;
    $name = $_SESSION['admin'] ?? null;
    // Если администратора удалили из базы — его старая сессия больше не действует.
    if ($name !== null && !$checked) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$name]);
        if (!$stmt->fetchColumn()) {
            unset($_SESSION['admin']);
            $name = null;
        }
        $checked = true;
    }
    return $name;
}

function require_admin(): void
{
    if (current_user() === null) {
        redirect('login.php');
    }
}

function format_minutes(int $min): string
{
    if ($min <= 0) {
        return '';
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    return trim(($h ? $h . ' ч ' : '') . ($m ? $m . ' мин' : ''));
}

function image_url(?string $image, string $prefix = ''): ?string
{
    return $image ? $prefix . 'uploads/' . rawurlencode($image) : null;
}
