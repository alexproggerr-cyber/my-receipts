<?php
// Шапка и подвал страниц. $base — путь до корня сайта ('' или '../').

function page_header(string $title, string $base = '', bool $admin = false): void
{
    ?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= $base ?>assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= $base ?>index.php">🍲 <?= e(SITE_NAME) ?></a>
        <nav>
            <?php if ($admin && current_user()): ?>
                <a href="<?= $base ?>admin/index.php">Рецепты</a>
                <a href="<?= $base ?>admin/categories.php">Категории</a>
                <a href="<?= $base ?>admin/edit.php">+ Добавить</a>
                <a href="<?= $base ?>index.php">На сайт</a>
                <a href="<?= $base ?>admin/logout.php">Выйти</a>
            <?php else: ?>
                <a href="<?= $base ?>index.php">Все рецепты</a>
                <a href="<?= $base ?>admin/index.php">Админка</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php if ($msg = flash()): ?>
    <div class="flash"><?= e($msg) ?></div>
<?php endif;
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-footer">
    <div class="container">© <?= date('Y') ?> <?= e(SITE_NAME) ?></div>
</footer>
</body>
</html>
<?php
}
