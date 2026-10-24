<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$q = trim((string) ($_GET['q'] ?? ''));
$cat = (int) ($_GET['cat'] ?? 0);

// В фильтре показываем только категории, в которых есть рецепты.
$allCategories = all_categories();
$categories = array_filter($allCategories, fn($c) => $c['cnt'] > 0);
$catName = null;
foreach ($allCategories as $c) {
    if ((int) $c['id'] === $cat) {
        $catName = $c['name'];
    }
}

$sql = 'SELECT r.*, c.name AS category FROM recipes r LEFT JOIN categories c ON c.id = r.category_id';
$params = [];
if ($cat) {
    $sql .= ' WHERE r.category_id = ?';
    $params[] = $cat;
}
$sql .= ' ORDER BY r.created_at DESC, r.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

// Поиск делаем в PHP: SQLite LIKE не умеет без учёта регистра для кириллицы.
if ($q !== '') {
    $pattern = '/' . preg_quote($q, '/') . '/iu';
    $recipes = array_filter($recipes, fn($r) =>
        preg_match($pattern, $r['title'] . ' ' . $r['description'] . ' ' . $r['ingredients']));
}

page_header('Все рецепты');
?>
<h1><?= $catName !== null ? e($catName) : 'Все рецепты' ?></h1>

<form class="filters" method="get">
    <?php if ($cat): ?><input type="hidden" name="cat" value="<?= $cat ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Поиск по названию или ингредиентам…">
    <button class="btn" type="submit">Найти</button>
</form>

<?php if ($categories): ?>
<div class="chips">
    <a class="chip <?= $cat ? '' : 'active' ?>" href="index.php">Все</a>
    <?php foreach ($categories as $c): ?>
        <a class="chip <?= (int) $c['id'] === $cat ? 'active' : '' ?>" href="index.php?cat=<?= (int) $c['id'] ?>"><?= e($c['name']) ?> <span class="hint"><?= (int) $c['cnt'] ?></span></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$recipes): ?>
    <p class="empty">Рецептов не найдено.</p>
<?php else: ?>
<div class="grid">
    <?php foreach ($recipes as $r): $img = image_url($r['image']); ?>
        <a class="card" href="recipe.php?id=<?= (int) $r['id'] ?>">
            <div class="card-img" <?= $img ? 'style="background-image:url(\'' . e($img) . '\')"' : '' ?>><?= $img ? '' : '🍽️' ?></div>
            <div class="card-body">
                <?php if ($r['category'] !== null): ?><span class="tag"><?= e($r['category']) ?></span><?php endif; ?>
                <h3><?= e($r['title']) ?></h3>
                <p><?= e($r['description']) ?></p>
                <div class="meta">
                    <?php if ($t = format_minutes((int) $r['cook_time'])): ?><span>⏱ <?= e($t) ?></span><?php endif; ?>
                    <?php if ($r['servings'] > 0): ?><span>👥 <?= (int) $r['servings'] ?> порц.</span><?php endif; ?>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_footer();
