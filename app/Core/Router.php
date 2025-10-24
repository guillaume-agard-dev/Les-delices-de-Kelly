<?php
namespace App\Core;

final class Router {
  /** @var array<string, array<string, callable|array{0: class-string, 1: string}>> */
  private array $routes = ['GET' => [], 'POST' => []];

  private string $base = '';

  /** Permet d'indiquer le chemin de base (ex: /recipes-project/public) */
  public function setBase(string $base): void {
    $this->base = rtrim($base, '/');
  }

  public function get(string $path, $handler): void { $this->map('GET', $path, $handler); }
  public function post(string $path, $handler): void { $this->map('POST', $path, $handler); }

  private function map(string $method, string $path, $handler): void {
    $this->routes[$method][$this->norm($path)] = $handler;
  }

  private function norm(string $p): string {
    $p = '/' . ltrim($p, '/');
    return rtrim($p, '/') ?: '/';
  }

  public function dispatch(string $uri, string $method): void {
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';

    // Retire le chemin de base si présent (ex: /recipes-project/public)
    if ($this->base !== '' && str_starts_with($path, $this->base)) {
      $path = substr($path, strlen($this->base));
      if ($path === '' || $path === false) {
        $path = '/';
      }
    }

    $path = $this->norm($path);
    $handler = $this->routes[$method][$path] ?? null;

    if (!$handler) {
      http_response_code(404);
      echo '404 Not Found';
      return;
    }

    if (is_callable($handler)) {
      $handler();
      return;
    }

    // [Controller::class, 'action']
    [$class, $action] = $handler;
    $controller = new $class();
    $controller->$action();
  }
}
