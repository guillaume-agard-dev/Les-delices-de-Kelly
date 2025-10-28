<?php
declare(strict_types=1);

/* --- Session sécurisée --- */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
  'lifetime' => 0, // session cookie
  'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/',
  'domain'   => '',      // défaut
  'secure'   => $https,  // true si HTTPS
  'httponly' => true,
  'samesite' => 'Lax',
]);

session_name('ldk_sess');
session_start();

/* Renforce la session au premier passage */
if (!isset($_SESSION['__init'])) {
  session_regenerate_id(true);
  $_SESSION['__init'] = 1;
}


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

// Auth
$router->get('/register', [App\Controllers\AuthController::class, 'register']);
$router->post('/register', [App\Controllers\AuthController::class, 'registerPost']);

$router->get('/login', [App\Controllers\AuthController::class, 'login']);
$router->post('/login', [App\Controllers\AuthController::class, 'loginPost']);

$router->post('/logout', [App\Controllers\AuthController::class, 'logout']); // bouton form POST

// Admin
$router->get('/admin', [App\Controllers\AdminController::class, 'index']);



// Dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
