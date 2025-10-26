<?php
namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;

final class View
{
    private static Environment $twig;

    public static function init(string $viewsPath): void
    {
        $loader = new FilesystemLoader($viewsPath);
        self::$twig = new Environment($loader, [
            'cache' => false,
            'autoescape' => 'html',
            'debug' => true,
        ]);
        self::$twig->addExtension(new DebugExtension());

        // Base path pour générer des liens corrects depuis /recipes-project/public
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        self::$twig->addGlobal('base', $base === '/' ? '' : $base);
        self::$twig->addGlobal('app_name', 'Les Délices de Kelly');

        // ➜ Globals utiles aux vues
        self::$twig->addGlobal('flash_ok',  \App\Core\Flash::pull('ok'));
        self::$twig->addGlobal('flash_err', \App\Core\Flash::pull('err'));
        self::$twig->addGlobal('auth_user', $_SESSION['user'] ?? null);
        self::$twig->addGlobal('csrf',      \App\Core\Csrf::token());
    }


    /** Rendu d’un template Twig */
    public static function render(string $template, array $data = []): void
    {
        echo self::$twig->render($template, $data);
    }
}
