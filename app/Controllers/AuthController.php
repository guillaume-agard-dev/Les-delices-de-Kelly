<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\DB;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Auth;

final class AuthController
{
    private function base(): string
    {
        return rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    }

    private function redirect(string $to): void
    {
        header('Location: ' . $this->base() . $to);
        exit;
    }

    /* -------- Register -------- */

    public function register(): void
    {
        if (Auth::check()) {
            Flash::set('ok', 'Vous êtes déjà connecté.');
            $this->redirect('/');
        }

        View::render('auth/register.twig', [
            'title' => 'Créer un compte',
        ]);
    }

    public function registerPost(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            Flash::set('err', 'Jeton CSRF invalide.');
            $this->redirect('/register');
        }
        if (Auth::check()) {
            $this->redirect('/');
        }

        $name     = trim((string)($_POST['name'] ?? ''));
        $email    = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['password_confirm'] ?? '');

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['name'] = 'Le nom doit contenir entre 2 et 80 caractères.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Mot de passe trop court (min. 8).';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        if (empty($errors)) {
            $exists = DB::query('SELECT id FROM users WHERE email = :email LIMIT 1', [
                'email' => $email
            ])->fetch();
            if ($exists) {
                $errors['email'] = 'Cet email est déjà utilisé.';
            }
        }

        if (!empty($errors)) {
            View::render('auth/register.twig', [
                'title'  => 'Créer un compte',
                'errors' => $errors,
                'old'    => ['name' => $name, 'email' => $email],
            ]);
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        DB::query(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)',
            ['name' => $name, 'email' => $email, 'hash' => $hash]
        );
        $id = (int)DB::$pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'    => $id,
            'name'  => $name,
            'email' => $email,
            'role'  => 'user',
        ];

        Flash::set('ok', 'Compte créé, bienvenue !');
        $this->redirect('/');
    }

    /* -------- Login -------- */

    public function login(): void
    {
        if (Auth::check()) {
            Flash::set('ok', 'Vous êtes déjà connecté.');
            $this->redirect('/');
        }

        $next = (string)($_GET['next'] ?? '');
        View::render('auth/login.twig', [
            'title' => 'Se connecter',
            'next'  => $next,
        ]);
    }

    public function loginPost(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            Flash::set('err', 'Jeton CSRF invalide.');
            $this->redirect('/login');
        }
        if (Auth::check()) {
            $this->redirect('/');
        }

        $email = (string)($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');
        $next  = (string)($_POST['next'] ?? '');

        if ($email === '' || $pass === '') {
            View::render('auth/login.twig', [
                'title'  => 'Se connecter',
                'errors' => ['form' => 'Merci de remplir tous les champs.'],
                'old'    => ['email' => $email],
                'next'   => $next,
            ]);
            return;
        }

        $ok = Auth::attempt($email, $pass);
        if (!$ok) {
            $remain = Auth::lockRemaining();
            $msg = $remain > 0
                ? "Trop d'essais. Réessayez dans {$remain} s."
                : 'Email ou mot de passe incorrect.';
            View::render('auth/login.twig', [
                'title'  => 'Se connecter',
                'errors' => ['form' => $msg],
                'old'    => ['email' => $email],
                'next'   => $next,
            ]);
            return;
        }

        Flash::set('ok', 'Connecté.');
        // Redirection "next" sécurisée (uniquement chemins internes)
        if ($next && str_starts_with($next, '/')) {
            $this->redirect($next);
        } else {
            $this->redirect('/');
        }
    }

    /* -------- Logout -------- */

    public function logout(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            Flash::set('err', 'Jeton CSRF invalide.');
            $this->redirect('/');
        }
        Auth::logout();
        Flash::set('ok', 'Déconnecté.');
        $this->redirect('/');
    }
}
