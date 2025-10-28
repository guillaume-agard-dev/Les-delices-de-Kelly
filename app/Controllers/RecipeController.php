<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\DB;

final class RecipeController
{
    /** Liste avec recherche + pagination */
    public function index(): void
    {
            $isLogged = \App\Core\Auth::check();

    // === Mode PUBLIC (non connecté) : 4 dernières, pas de filtres/pagination ===
    if (!$isLogged) {
        $recipes = \App\Core\DB::query(
            "SELECT id, title, slug, summary, image, diet, created_at
             FROM recipes
             WHERE published = 1
             ORDER BY created_at DESC, id DESC
             LIMIT 4"
        )->fetchAll();

        // Catégories par recette (badges)
        $catsByRecipe = [];
        if (!empty($recipes)) {
            $ids = array_column($recipes, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $rows = \App\Core\DB::query(
                "SELECT rc.recipe_id, c.name, c.slug
                 FROM recipe_category rc
                 JOIN categories c ON c.id = rc.category_id
                 WHERE rc.recipe_id IN ($ph)
                 ORDER BY c.name",
                $ids
            )->fetchAll();
            foreach ($rows as $row) {
                $catsByRecipe[(int)$row['recipe_id']][] = [
                    'name' => $row['name'],
                    'slug' => $row['slug']
                ];
            }
        }

        \App\Core\View::render('recipes/index.twig', [
            'title'        => 'Recettes',
            'recipes'      => $recipes,
            'cats'         => [],         // on masque le formulaire côté vue
            'catsByRecipe' => $catsByRecipe,
            'q'            => '',
            'cat'          => '',
            'diet'         => '',
            'page'         => 1,
            'pages'        => 1,
            'total'        => count($recipes),
            'per_page'     => 4,
            'limited'      => true,       // ⬅️ flag pour la vue
        ]);
        return;
    }

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

        $isLogged = \App\Core\Auth::check();
        if (!$isLogged) {
            // ID des 4 dernières publiées
            $top = \App\Core\DB::query(
                "SELECT id FROM recipes
                WHERE published = 1
                ORDER BY created_at DESC, id DESC
                LIMIT 4"
            )->fetchAll();
            $allowedIds = array_map(fn($r) => (int)$r['id'], $top);

            if (!in_array((int)$recipe['id'], $allowedIds, true)) {
                \App\Core\Flash::set('err', 'Connectez-vous pour consulter cette recette.');
                $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); if ($base === '/') $base = '';
                header('Location: ' . $base . '/login?next=' . $base . '/recipes/' . urlencode($slug));
                exit;
            }
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

        $comments = \App\Core\DB::query(
            "SELECT c.body, c.created_at, u.name
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.recipe_id = :rid
            ORDER BY c.created_at ASC",
            ['rid' => (int)$recipe['id']]
        )->fetchAll();

        View::render('recipes/show.twig', [
            'title'       => $recipe ? $recipe['title'] : 'Recette introuvable',
            'recipe'      => $recipe,
            'recipe_cats' => $recipeCats,
            'comments'    => $comments,
        ]);

    }

    public function commentPost($params): void
    {
        // Normaliser le slug venant du router
        $slug = is_array($params) ? (string)($params['slug'] ?? '') : (string)$params;

        // 1) Auth obligatoire
        if (!\App\Core\Auth::check()) {
            \App\Core\Flash::set('err', 'Veuillez vous connecter pour commenter.');
            $this->redirectToRecipe($slug, '#comments');
        }

        // 2) CSRF
        if (!\App\Core\Csrf::check($_POST['_csrf'] ?? null)) {
            \App\Core\Flash::set('err', 'CSRF invalide.');
            $this->redirectToRecipe($slug, '#comments');
        }

        // 3) Charger la recette (publiée)
        $recipe = \App\Core\DB::query(
            'SELECT id, slug, published FROM recipes WHERE slug = :s LIMIT 1',
            ['s' => $slug]
        )->fetch();
        if (!$recipe || (int)$recipe['published'] !== 1) {
            \App\Core\Flash::set('err', 'Recette introuvable.');
            $this->redirectToRecipes();
        }

        // 4) Valider le commentaire
        $body = trim((string)($_POST['body'] ?? ''));
        if (mb_strlen($body) < 3 || mb_strlen($body) > 2000) {
            \App\Core\Flash::set('err', 'Le commentaire doit faire entre 3 et 2000 caractères.');
            $this->redirectToRecipe($slug, '#comments');
        }

        // 5) Petit rate-limit anti spam (8s)
        if (!isset($_SESSION['last_comment_at']) || time() - (int)$_SESSION['last_comment_at'] > 8) {
            $_SESSION['last_comment_at'] = time();
        } else {
            \App\Core\Flash::set('err', 'Patientez quelques secondes avant de renvoyer un commentaire.');
            $this->redirectToRecipe($slug, '#comments');
        }

        // 6) Insérer
        $uid = (int)\App\Core\Auth::id(); // helper ci-dessous
        \App\Core\DB::query(
            'INSERT INTO comments (recipe_id, user_id, body) VALUES (:r, :u, :b)',
            ['r' => (int)$recipe['id'], 'u' => $uid, 'b' => $body]
        );

        \App\Core\Flash::set('ok', 'Commentaire publié.');
        $this->redirectToRecipe($slug, '#comments');
    }

    // Helpers redirection (mets-les en privé dans le contrôleur)
    private function redirectToRecipe(string $slug, string $anchor = ''): void
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); if ($base === '/') $base = '';
        header('Location: ' . $base . '/recipes/' . urlencode($slug) . $anchor);
        exit;
    }
    private function redirectToRecipes(): void
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); if ($base === '/') $base = '';
        header('Location: ' . $base . '/recipes');
        exit;
    }

}
