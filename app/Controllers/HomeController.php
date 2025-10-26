<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\DB;

final class HomeController
{
    public function index(): void
    {
        // Dernière recette (par date de création)
        $featured = DB::query(
            'SELECT id, title, slug, content, image_path, created_at
             FROM recipes
             ORDER BY created_at DESC
             LIMIT 1'
        )->fetch();

        // Catégories de la recette (si trouvée)
        $featuredCats = [];
        if ($featured) {
            $featuredCats = DB::query(
                'SELECT c.name, c.slug
                 FROM recipe_category rc
                 JOIN categories c ON c.id = rc.category_id
                 WHERE rc.recipe_id = :rid
                 ORDER BY c.name',
                ['rid' => $featured['id']]
            )->fetchAll();
        }

        View::render('home.twig', [
            'title'         => 'Accueil',
            'featured'      => $featured,
            'featured_cats' => $featuredCats,
        ]);
    }
}
