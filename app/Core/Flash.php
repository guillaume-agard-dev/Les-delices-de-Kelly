<?php
namespace App\Core;

final class Flash
{
    private const KEY = '__flash';

    /** Enregistre un message (ex: 'ok' ou 'err') */
    public static function set(string $type, string $message): void
    {
        $_SESSION[self::KEY][$type] = $message;
    }

    /** Récupère sans effacer (rarement utile) */
    public static function get(string $type): ?string
    {
        return $_SESSION[self::KEY][$type] ?? null;
    }

    /** Récupère ET efface (à utiliser pour l’affichage) */
    public static function pull(string $type): ?string
    {
        if (!isset($_SESSION[self::KEY][$type])) {
            return null;
        }
        $msg = $_SESSION[self::KEY][$type];
        unset($_SESSION[self::KEY][$type]);
        if (empty($_SESSION[self::KEY])) {
            unset($_SESSION[self::KEY]);
        }
        return $msg;
    }

    /** Efface tout */
    public static function clear(): void
    {
        unset($_SESSION[self::KEY]);
    }
}
