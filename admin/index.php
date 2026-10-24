<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require_admin();

$recipes = db()->query('SELECT r.id, r.title, r.image, r.created_at, c.name AS category
    FROM recipes r LEFT JOIN categories c ON c.id = r.category_id
    ORDER BY r.created_at DESC, r.id DESC')->fetchAll();

page_header('Админка', '../', true);
?>
<div class="toolbar">
    <h1>Рецепты (<?= count($recipes) ?>)</h1>
    <a class="btn" href="edit.php">+ Добавить рецепт</a>
</div>

<?php if (!$recipes): ?>
    <p class="empty">Пока нет ни одного рецепта. <a href="edit.php">Добавьте первый</a>.</p>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th></th><th>Название</th><th>Категория</th><th>Добавлен</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($recipes as $r): $img = image_url($r['image'], '../'); ?>
        <tr>
            <td><?php if ($img): ?><img class="thumb" src="<?= e($img) ?>" alt=""><?php else: ?><span class="thumb"></span><?php endif; ?></td>
            <td><a href="../recipe.php?id=<?= (int) $r['id'] ?>" target="_blank"><?= e($r['title']) ?></a></td>
            <td><?= e($r['category']) ?></td>
            <td><?= e(date('d.m.Y', strtotime($r['created_at']))) ?></td>
            <td>
                <div class="actions">
                    <a class="btn btn-secondary btn-small" href="edit.php?id=<?= (int) $r['id'] ?>">Изменить</a>
                    <form method="post" action="delete.php" onsubmit="return confirm('Удалить рецепт «<?= e(addslashes($r['title'])) ?>»?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="btn btn-danger btn-small" type="submit">Удалить</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php page_footer();
