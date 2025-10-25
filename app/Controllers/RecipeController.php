<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\DB;


final class RecipeController
{
    public function index(): void
    {
        $recipes = DB::query(
            'SELECT id, title, created_at FROM recipes ORDER BY created_at DESC LIMIT 20'
        )->fetchAll();

        View::render('recipes/index.twig', [
            'title'   => 'Recettes',
            'recipes' => $recipes,
        ]);
    }
}
