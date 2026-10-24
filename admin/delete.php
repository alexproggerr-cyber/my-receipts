<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
check_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT title, image FROM recipes WHERE id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch();

if ($r) {
    db()->prepare('DELETE FROM recipes WHERE id = ?')->execute([$id]);
    if ($r['image'] && is_file(UPLOAD_DIR . '/' . $r['image'])) {
        unlink(UPLOAD_DIR . '/' . $r['image']);
    }
    flash('Рецепт «' . $r['title'] . '» удалён.');
}
redirect('index.php');
