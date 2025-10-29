<?php
namespace App\Controllers;

use App\Core\View;

final class StaticController
{
    public function mentions(): void
    {
        View::render('static/mentions.twig', [
            'title' => 'Mentions légales',
            'updated_at' => '01/11/2025', // ajuste plus tard
        ]);
    }

    public function privacy(): void
    {
        View::render('static/privacy.twig', [
            'title' => 'Politique de confidentialité',
            'updated_at' => '01/11/2025',
        ]);
    }

    public function cookies(): void
    {
        View::render('static/cookies.twig', [
            'title' => 'Politique Cookies',
            'updated_at' => '01/11/2025',
        ]);
    }
}
