<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Router;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad(); // charge .env si présent

$router = new Router();

/** ➜ AJOUTER CETTE LIGNE : détecte le chemin de base /recipes-project/public */
$router->setBase(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));

$router->get('/', function () {
  $env = htmlspecialchars($_ENV['APP_ENV'] ?? 'n/a', ENT_QUOTES);
  echo <<<HTML
  <!doctype html>
  <html lang="fr">
  <head>
    <meta charset="utf-8">
    <title>Les délices de Kelly — Router OK</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
  </head>
  <body style="font-family: system-ui, sans-serif; max-width: 760px; margin: 40px auto; line-height:1.5;">
    <h1>Les délices de Kelly</h1>
    <p>Route <code>/</code> servie par le mini Router ✅</p>
    <p>Environnement actuel : <strong>{$env}</strong></p>
    <hr>
    <p style="color:#555">Prochaine étape : Twig + contrôleurs.</p>
  </body>
  </html>
  HTML;
});

/** Dispatch */
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
