<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$recipe = [
    'title' => '', 'category_id' => null, 'description' => '', 'ingredients' => '',
    'steps' => '', 'cook_time' => '', 'servings' => '', 'image' => null,
];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM recipes WHERE id = ?');
    $stmt->execute([$id]);
    $recipe = $stmt->fetch();
    if (!$recipe) {
        flash('Рецепт не найден.');
        redirect('index.php');
    }
}

// Сохраняет загруженную картинку и возвращает имя файла (или null, если файла нет).
function save_upload(array $file): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Не удалось загрузить файл (код ошибки ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_IMAGE_SIZE) {
        throw new RuntimeException('Картинка слишком большая — максимум 5 МБ.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('Можно загружать только JPG, PNG, WEBP или GIF.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $name = bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Не удалось сохранить картинку.');
    }
    return $name;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    foreach (['title', 'description', 'ingredients', 'steps'] as $f) {
        $recipe[$f] = trim((string) ($_POST[$f] ?? ''));
    }
    // Категория: либо выбрана из списка, либо введена новая (тогда создаём её).
    $newCategory = trim((string) ($_POST['new_category'] ?? ''));
    $recipe['category_id'] = (int) ($_POST['category_id'] ?? 0) ?: null;
    $recipe['cook_time'] = max(0, (int) ($_POST['cook_time'] ?? 0));
    $recipe['servings'] = max(0, (int) ($_POST['servings'] ?? 0));

    if ($recipe['title'] === '') {
        $errors[] = 'Введите название рецепта.';
    }
    if ($recipe['ingredients'] === '') {
        $errors[] = 'Добавьте хотя бы один ингредиент.';
    }
    if ($recipe['steps'] === '') {
        $errors[] = 'Опишите шаги приготовления.';
    }

    $newImage = null;
    if (!$errors) {
        try {
            $newImage = save_upload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        if ($newCategory !== '') {
            $recipe['category_id'] = category_id_by_name($newCategory);
        }
        $oldImage = $recipe['image'];
        $removeImage = !empty($_POST['remove_image']);
        $image = $newImage ?? ($removeImage ? null : $oldImage);

        $values = [
            $recipe['title'], $recipe['category_id'], $recipe['description'], $recipe['ingredients'],
            $recipe['steps'], $recipe['cook_time'], $recipe['servings'], $image,
        ];

        if ($id) {
            $values[] = $id;
            db()->prepare('UPDATE recipes SET title=?, category_id=?, description=?, ingredients=?, steps=?,
                cook_time=?, servings=?, image=? WHERE id=?')->execute($values);
        } else {
            db()->prepare('INSERT INTO recipes (title, category_id, description, ingredients, steps, cook_time, servings, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute($values);
        }

        // Старую картинку удаляем, если её заменили или убрали.
        if ($oldImage && $oldImage !== $image && is_file(UPLOAD_DIR . '/' . $oldImage)) {
            unlink(UPLOAD_DIR . '/' . $oldImage);
        }

        flash($id ? 'Рецепт сохранён.' : 'Рецепт добавлен.');
        redirect('index.php');
    }
}

$categories = all_categories();

page_header($id ? 'Редактирование' : 'Новый рецепт', '../', true);
?>
<h1><?= $id ? 'Редактирование рецепта' : 'Новый рецепт' ?></h1>

<?php if ($errors): ?>
    <div class="error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<form class="panel" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
        <label for="title">Название *</label>
        <input type="text" id="title" name="title" required value="<?= e($recipe['title']) ?>">
    </div>

    <div class="row">
        <div class="field">
            <label for="category_id">Категория <a class="hint" href="categories.php">— управлять</a></label>
            <select id="category_id" name="category_id">
                <option value="">— без категории —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $recipe['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="or">или новая:</div>
            <input type="text" name="new_category" placeholder="Название новой категории" aria-label="Новая категория"
                   value="<?= e($_POST['new_category'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="cook_time">Время, минут</label>
            <input type="number" id="cook_time" name="cook_time" min="0" value="<?= e((string) ($recipe['cook_time'] ?: '')) ?>">
        </div>
        <div class="field">
            <label for="servings">Порций</label>
            <input type="number" id="servings" name="servings" min="0" value="<?= e((string) ($recipe['servings'] ?: '')) ?>">
        </div>
    </div>

    <div class="field">
        <label for="description">Короткое описание</label>
        <textarea id="description" name="description" style="min-height:80px"><?= e($recipe['description']) ?></textarea>
    </div>

    <div class="field">
        <label for="ingredients">Ингредиенты * <span class="hint">— каждый с новой строки</span></label>
        <textarea id="ingredients" name="ingredients" required placeholder="Мука — 200 г&#10;Яйца — 2 шт."><?= e($recipe['ingredients']) ?></textarea>
    </div>

    <div class="field">
        <label for="steps">Шаги приготовления * <span class="hint">— каждый шаг с новой строки</span></label>
        <textarea id="steps" name="steps" required style="min-height:200px"><?= e($recipe['steps']) ?></textarea>
    </div>

    <div class="field">
        <label for="image">Фото блюда <span class="hint">— JPG, PNG, WEBP или GIF, до 5 МБ</span></label>
        <?php if ($recipe['image']): ?>
            <img class="current-image" src="<?= e(image_url($recipe['image'], '../')) ?>" alt="">
            <label class="hint"><input type="checkbox" name="remove_image" value="1"> Удалить текущее фото</label>
        <?php endif; ?>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
    </div>

    <button class="btn" type="submit"><?= $id ? 'Сохранить' : 'Добавить рецепт' ?></button>
    <a class="btn btn-secondary" href="index.php">Отмена</a>
</form>
<?php page_footer();
