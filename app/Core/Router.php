<?php
namespace App\Core;

final class Router {
  /** Routes exactes */
  private array $routes = ['GET' => [], 'POST' => []];
  /** Routes dynamiques (ex: /recipes/{slug}) */
  private array $patterns = ['GET' => [], 'POST' => []];

  private string $base = '';

  /** ➜ AJOUT: handler 404 optionnel */
  private $notFoundHandler = null;

  /** Indique le chemin de base (ex: /recipes-project/public) */
  public function setBase(string $base): void {
    $this->base = rtrim($base, '/');
  }

  /** ➜ AJOUT: setter pour le handler 404 */
  public function setNotFound($handler): void {
    $this->notFoundHandler = $handler;
  }

  public function get(string $path, $handler): void { $this->map('GET', $path, $handler); }
  public function post(string $path, $handler): void { $this->map('POST', $path, $handler); }

  private function map(string $method, string $path, $handler): void {
    $norm = $this->norm($path);

    if (str_contains($norm, '{')) {
      preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $norm, $m);
      $vars = $m[1] ?? [];

      $regex = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '([^/]+)', $norm);
      $regex = '#^' . $regex . '$#';

      $this->patterns[$method][] = [
        'regex'   => $regex,
        'vars'    => $vars,
        'handler' => $handler,
      ];
      return;
    }

    $this->routes[$method][$norm] = $handler;
  }

  private function norm(string $p): string {
    $p = '/' . ltrim($p, '/');
    return rtrim($p, '/') ?: '/';
  }

  public function dispatch(string $uri, string $method): void {
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';

    if ($this->base !== '' && str_starts_with($path, $this->base)) {
      $path = substr($path, strlen($this->base)) ?: '/';
    }

    $path = $this->norm($path);

    // 1) Match exact
    $handler = $this->routes[$method][$path] ?? null;
    if ($handler) { $this->invoke($handler, []); return; }

    // 2) Match pattern
    foreach ($this->patterns[$method] as $route) {
      if (preg_match($route['regex'], $path, $matches)) {
        array_shift($matches);
        $this->invoke($route['handler'], $matches);
        return;
      }
    }

    // 3) 404
    http_response_code(404);
    if ($this->notFoundHandler) {
      $this->invoke($this->notFoundHandler, []);
      return;
    }
    echo '404 Not Found';
  }

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
