<?php
namespace App\Controllers;

use App\Core\View;

final class AboutController
{
    public function index(): void
    {
        View::render('about.twig', [
            'title' => 'À propos',
        ]);
    }
}
