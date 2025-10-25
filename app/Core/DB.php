<?php
namespace App\Core;

use PDO;
use PDOException;

final class DB
{
    public static PDO $pdo;

    public static function init(array $cfg): void
    {
        try {
            self::$pdo = new PDO(
                $cfg['dsn'],
                $cfg['user'],
                $cfg['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo "Database connection error.";
            // En dev on peut décommenter pour debug :
            // echo "<pre>{$e->getMessage()}</pre>";
            exit;
        }
    }

    /** Petit helper pratique (requêtes préparées) */
    public static function query(string $sql, array $params = [])
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
