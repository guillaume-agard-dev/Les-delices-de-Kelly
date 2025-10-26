<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\DB;

final class RecipeController
{
    public function index(): void
    {
        // --- paramètres GET ---
        $q    = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);

        $perPage = 5; // nb d'éléments par page
        $where   = '';
        $params  = [];

        if ($q !== '') {
            $where  = 'WHERE title LIKE :q OR content LIKE :q';
            $params = ['q' => '%' . $q . '%'];
        }

        // --- total pour pagination ---
        $totalRow = DB::query("SELECT COUNT(*) AS c FROM recipes {$where}", $params)->fetch();
        $total    = (int)($totalRow['c'] ?? 0);
        $pages    = max(1, (int)ceil($total / $perPage));

        // si on demande une page trop grande, on clamp
        if ($page > $pages) { $page = $pages; }

        $offset = ($page - 1) * $perPage;

        // --- liste paginée ---
        // on injecte LIMIT/OFFSET validés (int) pour éviter les soucis de binding
        $sql = "SELECT id, title, slug, created_at
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

}
