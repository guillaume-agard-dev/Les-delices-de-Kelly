<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;

final class AdminRecipeController
{
    private function redirect(string $to): void {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base === '/') $base = '';
        header('Location: ' . $base . $to);
        exit;
    }

    /** Normalise l’ID venant du router (string "42" ou array ['id'=>42]) */
    private function paramId($params): int
    {
        if (is_array($params)) {
            return isset($params['id']) ? (int)$params['id'] : 0;
        }
        return (int)$params;
    }


    private static function slugify(string $name): string {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $s = preg_replace('~[^\\pL\\d]+~u', '-', $s);
        $s = trim($s, '-');
        $s = strtolower($s);
        $s = preg_replace('~[^-a-z0-9]+~', '', $s);
        return $s ?: 'recette';
    }
    private static function uniqueSlug(string $slug, ?int $ignoreId = null): string {
        $base = $slug; $i = 2;
        while (true) {
            $params = ['slug' => $slug];
            $sql = 'SELECT id FROM recipes WHERE slug = :slug';
            if ($ignoreId) { $sql .= ' AND id <> :id'; $params['id'] = $ignoreId; }
            $row = \App\Core\DB::query($sql.' LIMIT 1', $params)->fetch();
            if (!$row) return $slug;
            $slug = $base . '-' . $i++;
        }
    }
    private static function normList(?string $s): string {
        // normalise \r\n -> \n et trim global
        $s = trim((string)$s);
        $s = str_replace(["\r\n","\r"], "\n", $s);
        return $s;
    }
    private static function normTags(?string $s): string {
        $s = (string)$s;
        $parts = array_filter(array_map('trim', explode(',', $s)));
        return implode(', ', $parts);
    }


    public function store(): void
    {
        \App\Core\Auth::requireAdminOrRedirect();
        if (!\App\Core\Csrf::check($_POST['_csrf'] ?? null)) {
            \App\Core\Flash::set('err', 'CSRF invalide.');
            $this->redirect('/admin/recipes/create');
        }

        $title   = trim((string)($_POST['title'] ?? ''));
        $diet    = trim((string)($_POST['diet'] ?? ''));
        $summary = trim((string)($_POST['summary'] ?? ''));
        $ing     = self::normList($_POST['ingredients'] ?? '');
        $steps   = self::normList($_POST['steps'] ?? '');
        $tags    = self::normTags($_POST['tags'] ?? '');
        $prep    = ($_POST['prep_minutes'] ?? '') === '' ? null : max(0, (int)$_POST['prep_minutes']);
        $cook    = ($_POST['cook_minutes'] ?? '') === '' ? null : max(0, (int)$_POST['cook_minutes']);
        $serv    = ($_POST['servings'] ?? '') === '' ? null : max(0, (int)$_POST['servings']);
        $diff    = trim((string)($_POST['difficulty'] ?? ''));
        $pub     = isset($_POST['published']) && $_POST['published'] == '1' ? 1 : 0;
        $catIds  = array_map('intval', (array)($_POST['cat_ids'] ?? []));

        $errors = [];
        if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            $errors['title'] = 'Le titre doit contenir entre 2 et 120 caractères.';
        }
        if ($diet !== '' && !in_array($diet, ['vegan','vegetarien'], true)) {
            $errors['diet'] = 'Régime invalide.';
        }
        if (mb_strlen($summary) < 10) {
            $errors['summary'] = 'Résumé trop court.';
        }
        if ($ing === '') {
            $errors['ingredients'] = 'Liste d’ingrédients requise.';
        }
        if ($steps === '') {
            $errors['steps'] = 'Liste d’étapes requise.';
        }

        if (!empty($errors)) {
            $cats = \App\Core\DB::query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
            \App\Core\View::render('admin/recipes/form.twig', [
                'title'  => 'Nouvelle recette',
                'mode'   => 'create',
                'cats'   => $cats,
                'errors' => $errors,
                'old'    => [
                    'title' => $title, 'diet' => $diet, 'summary' => $summary,
                    'ingredients' => $ing, 'steps' => $steps, 'tags' => $tags,
                    'prep_minutes'=>$prep, 'cook_minutes'=>$cook, 'servings'=>$serv, 'difficulty'=>$diff,
                    'published' => (string)$pub, 'cat_ids' => $catIds,
                ],
            ]);
            return;
        }

        $slug = self::uniqueSlug(self::slugify($title));

        // insert recette (sans image pour l’instant ; on gérera l’upload à l’étape suivante)
        \App\Core\DB::query(
            'INSERT INTO recipes (title, slug, summary, diet, ingredients, steps, tags, image, published, prep_minutes, cook_minutes, servings, difficulty)
            VALUES (:t,:s,:sum,:diet,:ing,:steps,:tags,NULL,:pub,:prep,:cook,:serv,:diff)',
            [
                't'=>$title, 's'=>$slug, 'sum'=>$summary, 'diet'=>$diet ?: null,
                'ing'=>$ing, 'steps'=>$steps, 'tags'=>$tags ?: null,
                'pub'=>$pub, 'prep'=>$prep, 'cook'=>$cook, 'serv'=>$serv, 'diff'=>$diff ?: null,
            ]
        );
        $rid = (int)\App\Core\DB::$pdo->lastInsertId();

        // liaisons catégories
        if (!empty($catIds)) {
            $vals = [];
            $params = [];
            foreach ($catIds as $k => $cid) {
                $vals[] = '(:r'.$k.', :c'.$k.')';
                $params['r'.$k] = $rid;
                $params['c'.$k] = $cid;
            }
            \App\Core\DB::query('INSERT INTO recipe_category (recipe_id, category_id) VALUES '.implode(',', $vals), $params);
        }

        \App\Core\Flash::set('ok', 'Recette créée. Vous pouvez ajouter une image.');
        $this->redirect('/admin/recipes/'.$rid.'/edit');
    }


    /** GET /admin/recipes — Liste + filtres */
    public function index(): void
    {
        Auth::requireAdminOrRedirect();

        $q         = trim((string)($_GET['q'] ?? ''));
        $cat       = trim((string)($_GET['cat'] ?? ''));           // slug de catégorie
        $diet      = trim((string)($_GET['diet'] ?? ''));          // 'vegan' | 'vegetarien'
        $published = trim((string)($_GET['published'] ?? ''));     // '' | '1' | '0'
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 10;

        // Pour le select des catégories
        $cats = DB::query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();

        // WHERE/JOIN dynamiques
        $whereParts = [];
        $params = [];
        $join = '';

        if ($q !== '') {
            $whereParts[] = '(r.title LIKE :q OR r.summary LIKE :q OR r.ingredients LIKE :q OR r.tags LIKE :q)';
            $params['q'] = '%'.$q.'%';
        }
        if ($diet !== '') {
            $whereParts[] = 'r.diet = :diet';
            $params['diet'] = $diet;
        }
        if ($published !== '' && ($published === '0' || $published === '1')) {
            $whereParts[] = 'r.published = :pub';
            $params['pub'] = (int)$published;
        }
        if ($cat !== '') {
            $join .= ' JOIN recipe_category rc ON rc.recipe_id = r.id
                       JOIN categories c ON c.id = rc.category_id ';
            $whereParts[] = 'c.slug = :cat';
            $params['cat'] = $cat;
        }

        $where = $whereParts ? 'WHERE '.implode(' AND ', $whereParts) : '';

        // Total
        $totalRow = DB::query("SELECT COUNT(DISTINCT r.id) AS c
                               FROM recipes r
                               {$join}
                               {$where}", $params)->fetch();
        $total = (int)($totalRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }
        $offset = ($page - 1) * $perPage;

        // Liste paginée
        $sql = "SELECT DISTINCT r.id, r.title, r.slug, r.diet, r.published, r.created_at, r.image
                FROM recipes r
                {$join}
                {$where}
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $rows = DB::query($sql, $params)->fetchAll();

        // Catégories par recette (badges)
        $catsByRecipe = [];
        if (!empty($rows)) {
            $ids = array_column($rows, 'id');
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $catRows = DB::query(
                "SELECT rc.recipe_id, c.name, c.slug
                 FROM recipe_category rc
                 JOIN categories c ON c.id = rc.category_id
                 WHERE rc.recipe_id IN ($ph)
                 ORDER BY c.name",
                $ids
            )->fetchAll();
            foreach ($catRows as $r2) {
                $rid = (int)$r2['recipe_id'];
                $catsByRecipe[$rid][] = ['name'=>$r2['name'],'slug'=>$r2['slug']];
            }
        }

        View::render('admin/recipes/index.twig', [
            'title'        => 'Admin — Recettes',
            'rows'         => $rows,
            'cats'         => $cats,
            'catsByRecipe' => $catsByRecipe,
            'q'            => $q,
            'cat'          => $cat,
            'diet'         => $diet,
            'published'    => $published,
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
            'perPage'      => $perPage,
        ]);
    }

    public function create(): void
    {
        \App\Core\Auth::requireAdminOrRedirect();

        // catégories pour les cases à cocher
        $cats = \App\Core\DB::query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

        \App\Core\View::render('admin/recipes/form.twig', [
            'title' => 'Nouvelle recette',
            'mode'  => 'create',
            'cats'  => $cats,
            'old'   => [
                'title'        => '',
                'summary'      => '',
                'diet'         => '',
                'ingredients'  => '',
                'steps'        => '',
                'tags'         => '',
                'prep_minutes' => '',
                'cook_minutes' => '',
                'servings'     => '',
                'difficulty'   => '',
                'published'    => '1',
                'cat_ids'      => [],
            ],
            'errors' => [],
        ]);
    }

    public function edit($params): void
    {
        \App\Core\Auth::requireAdminOrRedirect();

        $id = $this->paramId($params);
        if ($id <= 0) {
            \App\Core\Flash::set('err', 'ID invalide.');
            $this->redirect('/admin/recipes');
        }

        $row = \App\Core\DB::query(
            'SELECT id, title, slug, summary, diet, ingredients, steps, tags, image, published,
                    prep_minutes, cook_minutes, servings, difficulty
            FROM recipes WHERE id = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        if (!$row) {
            \App\Core\Flash::set('err', 'Recette introuvable.');
            $this->redirect('/admin/recipes');
        }

        $cats = \App\Core\DB::query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
        $catLinks = \App\Core\DB::query(
            'SELECT category_id FROM recipe_category WHERE recipe_id = :rid',
            ['rid' => $id]
        )->fetchAll();
        $catIds = array_map(fn($r) => (int)$r['category_id'], $catLinks);

        \App\Core\View::render('admin/recipes/form.twig', [
            'title'  => 'Éditer la recette',
            'mode'   => 'edit',
            'cats'   => $cats,
            'errors' => [],
            'old'    => [
                'id'           => (int)$row['id'],
                'title'        => (string)$row['title'],
                'slug'         => (string)$row['slug'],
                'summary'      => (string)$row['summary'],
                'diet'         => $row['diet'],
                'ingredients'  => (string)$row['ingredients'],
                'steps'        => (string)$row['steps'],
                'tags'         => (string)($row['tags'] ?? ''),
                'image'        => (string)($row['image'] ?? ''),
                'published'    => (string)$row['published'],
                'prep_minutes' => $row['prep_minutes'],
                'cook_minutes' => $row['cook_minutes'],
                'servings'     => $row['servings'],
                'difficulty'   => (string)($row['difficulty'] ?? ''),
                'cat_ids'      => $catIds,
            ],
        ]);
    }

    public function update($params): void
    {
        \App\Core\Auth::requireAdminOrRedirect();
        if (!\App\Core\Csrf::check($_POST['_csrf'] ?? null)) {
            \App\Core\Flash::set('err', 'CSRF invalide.');
            $this->redirect('/admin/recipes');
        }

        $id = $this->paramId($params);
        if ($id <= 0) {
            \App\Core\Flash::set('err', 'ID invalide.');
            $this->redirect('/admin/recipes');
        }

        $existing = \App\Core\DB::query(
            'SELECT id, slug, image FROM recipes WHERE id = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        if (!$existing) {
            \App\Core\Flash::set('err', 'Recette introuvable.');
            $this->redirect('/admin/recipes');
        }

        // Champs
        $title   = trim((string)($_POST['title'] ?? ''));
        $diet    = trim((string)($_POST['diet'] ?? ''));
        $summary = trim((string)($_POST['summary'] ?? ''));
        $ing     = self::normList($_POST['ingredients'] ?? '');
        $steps   = self::normList($_POST['steps'] ?? '');
        $tags    = self::normTags($_POST['tags'] ?? '');
        $prep    = ($_POST['prep_minutes'] ?? '') === '' ? null : max(0, (int)$_POST['prep_minutes']);
        $cook    = ($_POST['cook_minutes'] ?? '') === '' ? null : max(0, (int)$_POST['cook_minutes']);
        $serv    = ($_POST['servings'] ?? '') === '' ? null : max(0, (int)$_POST['servings']);
        $diff    = trim((string)($_POST['difficulty'] ?? ''));
        $pub     = isset($_POST['published']) && $_POST['published'] == '1' ? 1 : 0;
        $catIds  = array_map('intval', (array)($_POST['cat_ids'] ?? []));

        // Validations
        $errors = [];
        if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            $errors['title'] = 'Le titre doit contenir entre 2 et 120 caractères.';
        }
        if ($diet !== '' && !in_array($diet, ['vegan','vegetarien'], true)) {
            $errors['diet'] = 'Régime invalide.';
        }
        if (mb_strlen($summary) < 10) {
            $errors['summary'] = 'Résumé trop court.';
        }
        if ($ing === '') {
            $errors['ingredients'] = 'Liste d’ingrédients requise.';
        }
        if ($steps === '') {
            $errors['steps'] = 'Liste d’étapes requise.';
        }

        // Gestion image (supprimer et/ou remplacer)
        $root = dirname(__DIR__, 2);
        $newImageRel = null;
        $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';

        if ($removeImage && $existing['image']) {
            $oldAbs = $root . '/public/' . $existing['image'];
            if (is_file($oldAbs)) @unlink($oldAbs);
            $newImageRel = null; // image sera mise à NULL en DB
        }

        if (isset($_FILES['image']) && is_array($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $size = (int)$_FILES['image']['size'];
                if ($size > 5 * 1024 * 1024) {
                    $errors['image'] = 'Fichier trop volumineux (max 5 Mo).';
                } else {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime  = $finfo->file($_FILES['image']['tmp_name']);
                    $extMap = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
                    if (!isset($extMap[$mime])) {
                        $errors['image'] = 'Format non supporté (JPEG/PNG/WebP uniquement).';
                    } else {
                        $ext = $extMap[$mime];
                        $dir = $root . '/public/uploads/recipes';
                        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

                        $filename  = $existing['slug'] . '.' . $ext; // on garde le slug courant
                        $targetAbs = $dir . '/' . $filename;
                        $targetRel = 'uploads/recipes/' . $filename;

                        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetAbs)) {
                            // On supprime l’ancienne si différente
                            if ($existing['image'] && $existing['image'] !== $targetRel) {
                                $oldAbs = $root . '/public/' . $existing['image'];
                                if (is_file($oldAbs)) @unlink($oldAbs);
                            }
                            $newImageRel = $targetRel;
                        } else {
                            $errors['image'] = 'Échec de l’envoi du fichier.';
                        }
                    }
                }
            } else {
                $errors['image'] = 'Upload invalide (code '.$_FILES['image']['error'].').';
            }
        }

        if (!empty($errors)) {
            $cats = \App\Core\DB::query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
            \App\Core\View::render('admin/recipes/form.twig', [
                'title'  => 'Éditer la recette',
                'mode'   => 'edit',
                'cats'   => $cats,
                'errors' => $errors,
                'old'    => [
                    'id' => $id, 'title' => $title, 'slug' => $existing['slug'],
                    'summary' => $summary, 'diet' => $diet,
                    'ingredients' => $ing, 'steps' => $steps, 'tags' => $tags,
                    'prep_minutes'=>$prep, 'cook_minutes'=>$cook, 'servings'=>$serv, 'difficulty'=>$diff,
                    'published' => (string)$pub, 'cat_ids' => $catIds,
                    'image' => $existing['image'], // on garde l’aperçu actuel si erreur
                ],
            ]);
            return;
        }

        // Update recette (slug inchangé)
        $sql = 'UPDATE recipes
                SET title=:t, summary=:sum, diet=:diet, ingredients=:ing, steps=:steps,
                    tags=:tags, published=:pub, prep_minutes=:prep, cook_minutes=:cook,
                    servings=:serv, difficulty=:diff';
        $params = [
            't'=>$title, 'sum'=>$summary, 'diet'=>$diet ?: null,
            'ing'=>$ing, 'steps'=>$steps, 'tags'=>$tags ?: null,
            'pub'=>$pub, 'prep'=>$prep, 'cook'=>$cook, 'serv'=>$serv, 'diff'=>$diff ?: null,
            'id'=>$id,
        ];

        if ($newImageRel !== null) {
            $sql .= ', image=:img';
            $params['img'] = $newImageRel;
        } elseif ($removeImage) {
            $sql .= ', image=NULL';
        }

        $sql .= ' WHERE id=:id';
        \App\Core\DB::query($sql, $params);

        // Maj catégories
        \App\Core\DB::query('DELETE FROM recipe_category WHERE recipe_id = :rid', ['rid' => $id]);
        if (!empty($catIds)) {
            $vals = [];
            $p = [];
            foreach ($catIds as $k => $cid) {
                $vals[] = '(:r'.$k.', :c'.$k.')';
                $p['r'.$k] = $id; $p['c'.$k] = $cid;
            }
            \App\Core\DB::query('INSERT INTO recipe_category (recipe_id, category_id) VALUES '.implode(',', $vals), $p);
        }

        \App\Core\Flash::set('ok', 'Recette mise à jour.');
        $this->redirect('/admin/recipes/'.$id.'/edit');
    }


    public function delete($params): void
    {
        \App\Core\Auth::requireAdminOrRedirect();
        if (!\App\Core\Csrf::check($_POST['_csrf'] ?? null)) {
            \App\Core\Flash::set('err', 'CSRF invalide.');
            $this->redirect('/admin/recipes');
        }

        $id = $this->paramId($params);
        if ($id <= 0) {
            \App\Core\Flash::set('err', 'ID invalide.');
            $this->redirect('/admin/recipes');
        }

        $row = \App\Core\DB::query('SELECT image FROM recipes WHERE id=:id', ['id'=>$id])->fetch();
        \App\Core\DB::query('DELETE FROM recipes WHERE id=:id', ['id'=>$id]);

        if ($row && $row['image']) {
            $abs = dirname(__DIR__, 2) . '/public/' . $row['image'];
            if (is_file($abs)) @unlink($abs);
        }

        \App\Core\Flash::set('ok', 'Recette supprimée.');
        $this->redirect('/admin/recipes');
    }


}
