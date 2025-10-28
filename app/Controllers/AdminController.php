<?php
namespace App\Controllers;

use App\Core\View;

final class AdminController
{
    public function index(): void
    {
        \App\Core\Auth::requireAdminOrRedirect();

        View::render('admin/dashboard.twig', [
            'title' => 'Admin — Tableau de bord',
        ]);
    }
}
