<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\View;
use App\Core\DB;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Initialiser Twig (répertoire des vues)
View::init(__DIR__ . '/../app/Views');

// ➜ INITIALISATION BDD (utilise .env)
DB::init([
    'dsn'  => sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'] ?? '127.0.0.1',
        $_ENV['DB_NAME'] ?? 'recipes'
    ),
    'user' => $_ENV['DB_USER'] ?? 'root',
    'pass' => $_ENV['DB_PASS'] ?? '',
]);

$router = new Router();
// Détecter le chemin de base (/recipes-project/public)
$router->setBase(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
// Route 404
$router->setNotFound([App\Controllers\ErrorController::class, 'notFound']);

// Route d'accueil -> contrôleur
$router->get('/', [App\Controllers\HomeController::class, 'index']);
// Route de Recipes -> contrôleur
$router->get('/recipes', [App\Controllers\RecipeController::class, 'index']);
// slug
$router->get('/recipes/{slug}', [App\Controllers\RecipeController::class, 'show']);
// About
$router->get('/about', [App\Controllers\AboutController::class, 'index']);



// Dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
