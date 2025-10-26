<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\DB;

final class RecipeController
{
    public function index(): void
    {
        $recipes = DB::query(
            'SELECT id, title, slug, created_at
            FROM recipes
            ORDER BY created_at DESC
            LIMIT 20'
        )->fetchAll();

        View::render('recipes/index.twig', [
            'title'   => 'Recettes',
            'recipes' => $recipes,
        ]);
    }


    /** Affichage d'une recette par slug */
    public function show(string $slug): void
    {
        $recipe = DB::query(
            'SELECT id, title, slug, content, created_at
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
