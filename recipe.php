<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$stmt = db()->prepare('SELECT r.*, c.name AS category FROM recipes r
    LEFT JOIN categories c ON c.id = r.category_id WHERE r.id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$r = $stmt->fetch();

if (!$r) {
    http_response_code(404);
    page_header('Не найдено');
    echo '<p class="empty">Такого рецепта нет. <a href="index.php">Вернуться к списку</a></p>';
    page_footer();
    exit;
}

page_header($r['title']);
$img = image_url($r['image']);
?>
<p><a href="index.php">← Все рецепты</a></p>

<?php if ($r['category'] !== null): ?>
    <a class="tag" href="index.php?cat=<?= (int) $r['category_id'] ?>"><?= e($r['category']) ?></a>
<?php endif; ?>
<h1><?= e($r['title']) ?></h1>

<?php if ($r['description'] !== ''): ?><p class="lead"><?= nl2br(e($r['description'])) ?></p><?php endif; ?>

<div class="meta" style="margin-bottom:20px">
    <?php if ($t = format_minutes((int) $r['cook_time'])): ?><span>⏱ <?= e($t) ?></span><?php endif; ?>
    <?php if ($r['servings'] > 0): ?><span>👥 <?= (int) $r['servings'] ?> порц.</span><?php endif; ?>
</div>

<?php if ($img): ?><img class="recipe-hero" src="<?= e($img) ?>" alt="<?= e($r['title']) ?>"><?php endif; ?>

<div class="recipe-cols">
    <section class="panel">
        <h2>Ингредиенты</h2>
        <ul class="ingredients">
            <?php foreach (lines($r['ingredients']) as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
        </ul>
    </section>
    <section class="panel">
        <h2>Приготовление</h2>
        <ol class="steps">
            <?php foreach (lines($r['steps']) as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
        </ol>
    </section>
</div>
<?php page_footer();
