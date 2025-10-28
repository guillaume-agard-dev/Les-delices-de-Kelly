<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Core\Flash;
use App\Core\Csrf;

final class AdminCategoryController
{
    private function redirect(string $to): void {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base === '/') $base = '';
        header('Location: ' . $base . $to);
        exit;
    }


    /** Normalise l’ID venant du router (string "42" ou array ['id'=>42]) */
    private function paramId($params): int
    {
        if (is_array($params)) {
            return isset($params['id']) ? (int)$params['id'] : 0;
        }
        return (int)$params;
    }


    private static function slugify(string $name): string {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $s = preg_replace('~[^\\pL\\d]+~u', '-', $s);
        $s = trim($s, '-');
        $s = strtolower($s);
        $s = preg_replace('~[^-a-z0-9]+~', '', $s);
        return $s ?: 'categorie';
    }

    /** Rend unique un slug (ajoute -2, -3... si besoin) */
    private static function uniqueSlug(string $slug, ?int $ignoreId = null): string {
        $base = $slug;
        $i = 2;
        while (true) {
            $params = ['slug' => $slug];
            $sql = 'SELECT id FROM categories WHERE slug = :slug';
            if ($ignoreId) {
                $sql .= ' AND id <> :id';
                $params['id'] = $ignoreId;
            }
            $row = DB::query($sql . ' LIMIT 1', $params)->fetch();
            if (!$row) return $slug;
            $slug = $base . '-' . $i++;
        }
    }

    /** GET /admin/categories */
    public function index(): void
    {
        Auth::requireAdminOrRedirect();

        // Liste + compteur de recettes liées
        $rows = DB::query(
            'SELECT c.id, c.name, c.slug, COUNT(rc.recipe_id) AS nrecipes
             FROM categories c
             LEFT JOIN recipe_category rc ON rc.category_id = c.id
             GROUP BY c.id
             ORDER BY c.name'
        )->fetchAll();

        View::render('admin/categories/index.twig', [
            'title' => 'Admin — Catégories',
            'rows'  => $rows,
        ]);
    }

    /** POST /admin/categories/create */
    public function create(): void
    {
        Auth::requireAdminOrRedirect();
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Flash::set('err', 'CSRF invalide.');
            $this->redirect('/admin/categories');
        }

        $name = trim((string)($_POST['name'] ?? ''));
        if (mb_strlen($name) < 2) {
            Flash::set('err', 'Nom trop court.');
            $this->redirect('/admin/categories');
        }

        $slug = self::slugify($name);
        $slug = self::uniqueSlug($slug);

        DB::query('INSERT INTO categories (name, slug) VALUES (:n, :s)', [
            'n' => $name, 's' => $slug
        ]);

        Flash::set('ok', 'Catégorie créée.');
        $this->redirect('/admin/categories');
    }

    /** POST /admin/categories/{id}/rename */
    public function rename($params): void
    {
        Auth::requireAdminOrRedirect();
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Flash::set('err', 'CSRF invalide.');
            $this->redirect('/admin/categories');
        }

        $id = $this->paramId($params);
        $name = trim((string)($_POST['name'] ?? ''));

        if ($id <= 0 || mb_strlen($name) < 2) {
            Flash::set('err', 'Paramètres invalides.');
            $this->redirect('/admin/categories');
        }

        $slug = self::uniqueSlug(self::slugify($name), $id);

        DB::query('UPDATE categories SET name = :n, slug = :s WHERE id = :id', [
            'n' => $name, 's' => $slug, 'id' => $id
        ]);

        Flash::set('ok', 'Catégorie renommée.');
        $this->redirect('/admin/categories');
    }

    /** POST /admin/categories/{id}/delete */
    public function delete($params): void
    {
        Auth::requireAdminOrRedirect();
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Flash::set('err', 'CSRF invalide.');
            $this->redirect('/admin/categories');
        }

        $id = $this->paramId($params);
        if ($id <= 0) {
            Flash::set('err', 'ID invalide.');
            $this->redirect('/admin/categories');
        }


        // La FK recipe_category ON DELETE CASCADE supprimera les liaisons
        DB::query('DELETE FROM categories WHERE id = :id', ['id' => $id]);

        Flash::set('ok', 'Catégorie supprimée.');
        $this->redirect('/admin/categories');
    }
}
