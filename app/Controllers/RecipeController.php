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
        $cat  = isset($_GET['cat']) ? trim((string)$_GET['cat']) : '';
        $diet = isset($_GET['diet']) ? trim((string)$_GET['diet']) : ''; // 'vegan' | 'vegetarien'
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 5;

        // Catégories pour le <select>
        $cats = DB::query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();

        // WHERE/JOIN dynamiques
        $whereParts = ['recipes.published = 1'];
        $params = [];
        $join = '';

        if ($q !== '') {
            // Recherche sur title + summary + ingredients + tags
            $whereParts[] = '(recipes.title LIKE :q OR recipes.summary LIKE :q OR recipes.ingredients LIKE :q OR recipes.tags LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($diet !== '') {
            $whereParts[] = 'recipes.diet = :diet';
            $params['diet'] = $diet; // valeurs: 'vegan' | 'vegetarien'
        }
        if ($cat !== '') {
            $join .= ' JOIN recipe_category rc ON rc.recipe_id = recipes.id
                    JOIN categories c ON c.id = rc.category_id ';
            $whereParts[] = 'c.slug = :cat';
            $params['cat'] = $cat;
        }
        $where = 'WHERE ' . implode(' AND ', $whereParts);

        // Total + pagination
        $totalRow = DB::query("SELECT COUNT(DISTINCT recipes.id) AS c
                            FROM recipes
                            {$join}
                            {$where}", $params)->fetch();
        $total = (int)($totalRow['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }
        $offset = ($page - 1) * $perPage;

        // Liste paginée (nouvelles colonnes)
        $sql = "SELECT DISTINCT recipes.id, recipes.title, recipes.slug, recipes.summary,
                            recipes.image, recipes.diet, recipes.created_at
                FROM recipes
                {$join}
                {$where}
                ORDER BY recipes.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $recipes = DB::query($sql, $params)->fetchAll();

        // Catégories par recette (pour badges)
        $catsByRecipe = [];
        if (!empty($recipes)) {
            $ids = array_column($recipes, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $rows = DB::query(
                "SELECT rc.recipe_id, c.name, c.slug
                FROM recipe_category rc
                JOIN categories c ON c.id = rc.category_id
                WHERE rc.recipe_id IN ($ph)
                ORDER BY c.name",
                $ids
            )->fetchAll();
            foreach ($rows as $row) {
                $rid = (int)$row['recipe_id'];
                $catsByRecipe[$rid][] = ['name' => $row['name'], 'slug' => $row['slug']];
            }
        }

        View::render('recipes/index.twig', [
            'title'        => 'Recettes',
            'recipes'      => $recipes,
            'q'            => $q,
            'cat'          => $cat,
            'diet'         => $diet,
            'cats'         => $cats,
            'catsByRecipe' => $catsByRecipe,
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
            'per_page'     => $perPage,
        ]);
    }



    /** Détail d'une recette par slug */
    public function show(string $slug): void
    {
        $recipe = DB::query(
            'SELECT id, title, slug, summary, diet,
                    prep_minutes, cook_minutes, servings, difficulty,
                    ingredients, steps, tags,
                    image, published, created_at, updated_at
            FROM recipes
            WHERE slug = :slug AND published = 1
            LIMIT 1',
            ['slug' => $slug]
        )->fetch();

        if (!$recipe) {
            http_response_code(404);
        }

        // Catégories de la recette (si trouvée)
        $recipeCats = [];
        if ($recipe) {
            $recipeCats = DB::query(
                'SELECT c.name, c.slug
                FROM recipe_category rc
                JOIN categories c ON c.id = rc.category_id
                WHERE rc.recipe_id = :rid
                ORDER BY c.name',
                ['rid' => $recipe['id']]
            )->fetchAll();
        }

        View::render('recipes/show.twig', [
            'title'       => $recipe ? $recipe['title'] : 'Recette introuvable',
            'recipe'      => $recipe,
            'recipe_cats' => $recipeCats,
        ]);

    }
}
