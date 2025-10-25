<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\View;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Initialiser Twig (répertoire des vues)
View::init(__DIR__ . '/../app/Views');

$router = new Router();
// Détecter le chemin de base (/recipes-project/public)
$router->setBase(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));

// Route d'accueil -> contrôleur
$router->get('/', [App\Controllers\HomeController::class, 'index']);
// Route de Recipes -> contrôleur
$router->get('/recipes', [App\Controllers\RecipeController::class, 'index']);


// Dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
