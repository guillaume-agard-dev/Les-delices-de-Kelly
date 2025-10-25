<?php
namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;

final class View
{
    private static Environment $twig;

    /** Initialise Twig et expose quelques variables globales */
    public static function init(string $viewsPath): void
    {
        $loader = new FilesystemLoader($viewsPath);
        self::$twig = new Environment($loader, [
            'cache' => false,         // tu pourras mettre storage/cache en prod
            'autoescape' => 'html',
            'debug' => true,
        ]);
        self::$twig->addExtension(new DebugExtension());

        // Base path pour générer des liens corrects depuis /recipes-project/public
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        self::$twig->addGlobal('base', $base === '/' ? '' : $base);

        // Petit bonus : nom d’app accessible dans les templates
        self::$twig->addGlobal('app_name', 'Les délices de Kelly');
    }

    /** Rendu d’un template Twig */
    public static function render(string $template, array $data = []): void
    {
        echo self::$twig->render($template, $data);
    }
}
