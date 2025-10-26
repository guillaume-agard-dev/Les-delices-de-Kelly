<?php
namespace App\Core;

final class Auth
{
    private const MAX_TRIES    = 5;    // nb d'essais avant verrou
    private const LOCK_SECONDS = 120;  // durée du verrou (2 min)

    /** Normalise l'email (trim + lowercase) */
    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** Utilisateur courant (extrait de la session) */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** true si connecté */
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    /** true si connecté et rôle admin */
    public static function isAdmin(): bool
    {
        return isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? 'user') === 'admin';
    }

    /** Déconnexion sécurisée */
    public static function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }

    /** Délai de verrou restant (en secondes), 0 si aucun */
    public static function lockRemaining(): int
    {
        $until = $_SESSION['login_lock_until'] ?? 0;
        $remain = $until - time();
        return $remain > 0 ? $remain : 0;
    }

    /**
     * Tentative de connexion.
     * @return bool true si succès, false sinon (vérifie lockRemaining() pour savoir si verrou)
     */
    public static function attempt(string $email, string $password): bool
    {
        // Verrou actif ?
        if (self::lockRemaining() > 0) {
            return false;
        }

        $email = self::normalizeEmail($email);

        // Récupère l'utilisateur
        $row = DB::query(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        )->fetch();

        $ok = $row && password_verify($password, $row['password_hash'] ?? '');

        if (!$ok) {
            // Échec → incrémente essais et pose verrou si besoin
            $tries = (int)($_SESSION['login_tries'] ?? 0);
            $tries++;
            if ($tries >= self::MAX_TRIES) {
                unset($_SESSION['login_tries']);
                $_SESSION['login_lock_until'] = time() + self::LOCK_SECONDS;
            } else {
                $_SESSION['login_tries'] = $tries;
            }
            return false;
        }

        // Succès → reset compteur/lock
        unset($_SESSION['login_tries'], $_SESSION['login_lock_until']);

        // Initialise la session "utilisateur"
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'    => (int)$row['id'],
            'name'  => (string)$row['name'],
            'email' => (string)$row['email'],
            'role'  => (string)$row['role'],
        ];

        return true;
    }
}
