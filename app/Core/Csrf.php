<?php
namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    /** Retourne le token courant (et le génère s'il n'existe pas) */
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /**
     * Vérifie le token envoyé. Si OK, on régénère (anti re-play).
     * @return bool
     */
    public static function check(?string $token): bool
    {
        if (!$token || empty($_SESSION[self::KEY])) {
            return false;
        }
        $ok = hash_equals($_SESSION[self::KEY], $token);
        if ($ok) {
            // rotation du token après succès
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $ok;
    }
}
