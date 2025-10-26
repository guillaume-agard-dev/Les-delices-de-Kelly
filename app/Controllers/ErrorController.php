<?php
namespace App\Controllers;

use App\Core\View;

final class ErrorController
{
    public function notFound(): void
    {
        // Le Router met déjà 404 ; on double par sécurité.
        http_response_code(404);
        View::render('errors/404.twig', [
            'title' => 'Page introuvable',
        ]);
    }
}
