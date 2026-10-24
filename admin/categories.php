<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require_admin();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));

    // Проверяем, не занято ли название другой категорией.
    $taken = function (string $name, int $exceptId = 0): bool {
        $stmt = db()->prepare('SELECT 1 FROM categories WHERE name = ? AND id <> ?');
        $stmt->execute([$name, $exceptId]);
        return (bool) $stmt->fetchColumn();
    };

    if ($action === 'add') {
        if ($name === '') {
            $error = 'Введите название категории.';
        } elseif ($taken($name)) {
            $error = 'Категория «' . $name . '» уже есть.';
        } else {
            db()->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
            flash('Категория «' . $name . '» добавлена.');
            redirect('categories.php');
        }
    } elseif ($action === 'rename' && $id) {
        if ($name === '') {
            $error = 'Название не может быть пустым.';
        } elseif ($taken($name, $id)) {
            $error = 'Категория «' . $name . '» уже есть.';
        } else {
            db()->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $id]);
            flash('Категория переименована.');
            redirect('categories.php');
        }
    } elseif ($action === 'delete' && $id) {
        // Рецепты не удаляются — у них просто пропадёт категория (ON DELETE SET NULL).
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        flash('Категория удалена.');
        redirect('categories.php');
    }
}

$categories = all_categories();

page_header('Категории', '../', true);
?>
<div class="toolbar">
    <h1>Категории (<?= count($categories) ?>)</h1>
</div>

<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<form class="panel inline-form" method="post" style="margin-bottom:24px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <input type="text" name="name" placeholder="Новая категория, например «Десерты»" required
           value="<?= e(($_POST['action'] ?? '') === 'add' ? ($_POST['name'] ?? '') : '') ?>">
    <button class="btn" type="submit">+ Добавить</button>
</form>

<?php if (!$categories): ?>
    <p class="empty">Категорий пока нет.</p>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Название</th><th>Рецептов</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
        <tr>
            <td>
                <form class="inline-form" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="rename">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <input type="text" name="name" value="<?= e($c['name']) ?>" required aria-label="Название категории">
                    <button class="btn btn-secondary btn-small" type="submit">Сохранить</button>
                </form>
            </td>
            <td><a href="../index.php?cat=<?= (int) $c['id'] ?>" target="_blank"><?= (int) $c['cnt'] ?></a></td>
            <td>
                <form method="post" onsubmit="return confirm(<?= e(json_encode(
                    'Удалить категорию «' . $c['name'] . '»?' . ($c['cnt'] ? ' Рецепты останутся, но без категории.' : ''),
                    JSON_UNESCAPED_UNICODE)) ?>)">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button class="btn btn-danger btn-small" type="submit">Удалить</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php page_footer();
