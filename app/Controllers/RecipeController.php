<?php
namespace App\Controllers;

use App\Core\View;

final class RecipeController
{
    public function index(): void
    {
        // Données factices pour tester le rendu (on branchera la BDD ensuite)
        $recipes = [
            ['id' => 1, 'title' => 'Tarte aux pommes', 'created_at' => '2025-10-01 10:15:00'],
            ['id' => 2, 'title' => 'Quiche lorraine', 'created_at' => '2025-10-05 12:30:00'],
            ['id' => 3, 'title' => 'Curry de légumes', 'created_at' => '2025-10-10 18:45:00'],
        ];

        View::render('recipes/index.twig', [
            'title'   => 'Recettes',
            'recipes' => $recipes,
        ]);
    }
}
