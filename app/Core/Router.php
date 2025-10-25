<?php
namespace App\Core;

final class Router {
  /** Routes exactes */
  private array $routes = ['GET' => [], 'POST' => []];
  /** Routes avec paramètres (ex: /recipes/{slug}) */
  private array $patterns = ['GET' => [], 'POST' => []];

  private string $base = '';

  /** Indique le chemin de base (ex: /recipes-project/public) */
  public function setBase(string $base): void {
    $this->base = rtrim($base, '/');
  }

  public function get(string $path, $handler): void { $this->map('GET', $path, $handler); }
  public function post(string $path, $handler): void { $this->map('POST', $path, $handler); }

  private function map(string $method, string $path, $handler): void {
    $norm = $this->norm($path);

    // Si la route contient des {param}, on génère une regex
    if (str_contains($norm, '{')) {
      // Récupère les noms des variables {slug}, {id}, etc.
      preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $norm, $m);
      $vars = $m[1] ?? [];

      // Remplace {var} par un groupe qui capte tout sauf le slash
      $regex = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '([^/]+)', $norm);
      $regex = '#^' . $regex . '$#';

      $this->patterns[$method][] = [
        'regex'   => $regex,
        'vars'    => $vars,
        'handler' => $handler,
      ];
      return;
    }

    // Sinon, route exacte
    $this->routes[$method][$norm] = $handler;
  }

  private function norm(string $p): string {
    $p = '/' . ltrim($p, '/');
    return rtrim($p, '/') ?: '/';
  }

  public function dispatch(string $uri, string $method): void {
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';

    // Retirer le base-path si présent (ex: /recipes-project/public)
    if ($this->base !== '' && str_starts_with($path, $this->base)) {
      $path = substr($path, strlen($this->base)) ?: '/';
    }

    $path = $this->norm($path);

    // 1) Tentative de match exact
    $handler = $this->routes[$method][$path] ?? null;
    if ($handler) {
      $this->invoke($handler, []);
      return;
    }

    // 2) Tentative de match via patterns dynamiques
    foreach ($this->patterns[$method] as $route) {
      if (preg_match($route['regex'], $path, $matches)) {
        array_shift($matches); // on enlève la capture complète
        $this->invoke($route['handler'], $matches);
        return;
      }
    }

    http_response_code(404);
    echo '404 Not Found';
  }

  /** Appelle le handler (callable ou [Controller::class, "action"]) */
  private function invoke($handler, array $params): void {
    if (is_callable($handler)) {
      $handler(...$params);
      return;
    }
    [$class, $action] = $handler;
    $controller = new $class();
    $controller->$action(...$params);
  }
}
