<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\DB;

final class RecipeController
{
    /** Liste avec recherche + pagination */
    public function index(): void
    {
        $q    = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);

        $perPage = 5;
        $where   = '';
        $params  = [];

        if ($q !== '') {
            $where  = 'WHERE title LIKE :q OR content LIKE :q';
            $params = ['q' => '%' . $q . '%'];
        }

        $totalRow = DB::query("SELECT COUNT(*) AS c FROM recipes {$where}", $params)->fetch();
        $total    = (int)($totalRow['c'] ?? 0);
        $pages    = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT id, title, slug, image_path, created_at
                FROM recipes
                {$where}
                ORDER BY created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $recipes = DB::query($sql, $params)->fetchAll();

        View::render('recipes/index.twig', [
            'title'    => 'Recettes',
            'recipes'  => $recipes,
            'q'        => $q,
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'per_page' => $perPage,
        ]);
    }

    /** Détail d'une recette par slug */
    public function show(string $slug): void
    {
        $recipe = DB::query(
            'SELECT id, title, slug, content, image_path, created_at
             FROM recipes
             WHERE slug = :slug
             LIMIT 1',
            ['slug' => $slug]
        )->fetch();

        if (!$recipe) {
            http_response_code(404);
        }

        View::render('recipes/show.twig', [
            'title'  => $recipe ? $recipe['title'] : 'Recette introuvable',
            'recipe' => $recipe,
        ]);
    }
}
