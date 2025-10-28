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

    /* Les méthodes create/store/edit/update/delete/togglePublish arrivent aux étapes suivantes */
}
