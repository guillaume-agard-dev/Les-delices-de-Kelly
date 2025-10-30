# Les Délices de Kelly — Monolithe PHP (MVC maison)

Site de partage de recettes végétariennes & vegan.  
**Visiteur** : accès aux **4 dernières** recettes + lecture des commentaires.  
**Utilisateur connecté** : accès à **toutes** les recettes + ajout de **commentaires**.  
**Admin** : CRUD Catégories & Recettes (upload d’image), garde d’accès, tableau de bord.  
Pages légales : Mentions légales, Politique de confidentialité, Cookies (cookies techniques uniquement).

---

## Sommaire
- [Fonctionnalités](#fonctionnalités)
- [Architecture](#architecture)
- [Arborescence](#arborescence)
- [Technologies utilisées](#technologies-utilisées)
- [Prérequis](#prérequis)
- [Installation (dev local)](#installation-dev-local)
- [Configuration](#configuration)
- [Données de base (optionnel)](#données-de-base-optionnel)
- [Démarrage & URLs utiles](#démarrage--urls-utiles)
- [Sécurité](#sécurité)
- [Accessibilité & responsive](#accessibilité--responsive)
- [Pages légales & RGPD](#pages-légales--rgpd)
- [Déploiement (Apache / o2switch)](#déploiement-apache--o2switch)
- [Dépannage (FAQ)](#dépannage-faq)
- [Roadmap](#roadmap)
- [Crédits & licence](#crédits--licence)

---

## Fonctionnalités
- **Public (non connecté)**
  - Page `/recipes` limitée aux **4 dernières** recettes publiées (pas de filtres/pagination).
  - Lecture des **commentaires** sur les recettes accessibles.
- **Utilisateur connecté**
  - Liste complète des recettes + **recherche** / **filtres** / **pagination**.
  - **Ajout de commentaire** sur une fiche recette.
- **Admin**
  - **Dashboard** `/admin`.
  - **Catégories** : liste, ajout, renommage (slug unique), suppression.
  - **Recettes** : liste filtrable, création/édition/suppression, **upload image** (JPEG/PNG/WebP ≤ 5 Mo, renommée par `slug.ext`), gestion des catégories liées.
  - Accès protégé par `Auth::requireAdminOrRedirect()`.
- **Légal**
  - `/mentions-legales`, `/politique-de-confidentialite`, `/cookies`.
  - Cookies techniques uniquement → **pas de bannière**.

---

## Architecture
- **Type** : Monolithe **PHP 8.x** avec **MVC maison**.
- **Front Controller** : `public/index.php` + `.htaccess` (réécriture).
- **Routing → Controllers → Vues (Twig) → DB (PDO)**.
- **Core** : `Router`, `View` (Twig + baseURL), `DB` (PDO + requêtes préparées), `Auth` (sessions, rôles), `Csrf`, `Flash`.

---

## Arborescence
```
/recipes-project
├── composer.json
├── vendor/
├── app/
│   ├── Core/ { Router.php, View.php, DB.php, Auth.php, Csrf.php, Flash.php }
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   ├── RecipeController.php
│   │   ├── StaticController.php
│   │   ├── AuthController.php
│   │   ├── AdminController.php
│   │   ├── AdminCategoryController.php
│   │   └── AdminRecipeController.php
│   └── Views/
│       ├── layout.twig
│       ├── partials/ { navbar.twig, flashes.twig }
│       ├── home/ { index.twig }
│       ├── recipes/ { index.twig, show.twig }
│       ├── admin/
│       │   ├── dashboard.twig
│       │   ├── categories/ { index.twig }
│       │   └── recipes/ { index.twig, form.twig }
│       └── static/ { mentions.twig, privacy.twig, cookies.twig }
├── config/
│   ├── db.php
│   └── app.php
├── public/
│   ├── .htaccess
│   ├── index.php
│   ├── assets/
│   │   ├── app.css
│   │   ├── app.js
│   │   ├── img/ { logo.png, hero.jpg, ... }
│   │   └── icons/ { favicon.ico, favicon.svg }
│   └── uploads/
│       └── recipes/  (images uploadées)
├── storage/
│   └── cache/ { twig/ (si activé) }
├── database/
│   └── schema.sql
├── .gitignore
└── README.md
```

---

## Technologies utilisées
- **Serveur** : PHP ≥ 8.1, Apache (mod_rewrite), MySQL/MariaDB.
- **Back** : PHP (MVC maison), **PDO** (requêtes préparées).
- **Vues** : **Twig** (auto-escape).
- **Front** : HTML5, CSS3 (`public/assets/app.css`), JS vanilla (`public/assets/app.js`).
- **Dépendances** : **Composer** (Twig).
- **Outils** : XAMPP (dev), phpMyAdmin, VS Code, Git/GitHub.
- **Hébergement cible** : o2switch (LAMP).

---

## Prérequis
- PHP ≥ **8.1** avec extensions : `pdo_mysql`, `mbstring`, `fileinfo`.
- Apache avec **mod_rewrite** activé.
- MySQL/MariaDB.
- Composer.

---

## Installation (dev local)
1. **Cloner** le repo, se placer à la racine.
2. Installer les dépendances :
   ```bash
   composer install
   ```
3. Créer la base MySQL et **importer** le schéma (tables `users`, `recipes`, `categories`, `recipe_category`, `comments`).
4. Configurer la connexion dans `config/db.php`.
5. Créer le dossier d’uploads et donner les droits d’écriture :
   ```
   public/uploads/recipes
   ```
6. Lancer sous XAMPP/MAMP :  
   `http://localhost/recipes-project/public/`

---

## Configuration
### `config/db.php` (exemple)
```php
<?php
return [
  'dsn'  => 'mysql:host=127.0.0.1;dbname=recipes;charset=utf8mb4',
  'user' => 'root',
  'pass' => '',
  'options' => [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ],
];
```
### `.htaccess` (dans `public/`)
```apache
Options -Indexes

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /recipes-project/public/

  # Redirige tout vers index.php (sauf fichiers et dossiers existants)
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ index.php [QSA,L]
</IfModule>
```
> Adapte `RewriteBase` si le projet n’est pas dans `/recipes-project/public/`.

---

## Données de base (database/shema.sql)
- Vous pouvez pré-remplir quelques **catégories** et **recettes** pour les démos.
- Promouvoir un utilisateur en **admin** :
  ```sql
  UPDATE users SET role = 'admin' WHERE email = 'votre.email@example.com';
  ```

---

## Démarrage & URLs utiles
- Accueil : `/`
- Recettes (public limité ou complet si connecté) : `/recipes`
- Détail recette : `/recipes/{slug}`
- Auth : `/login`, `/register`, `/logout`
- Admin : `/admin`
  - Catégories : `/admin/categories`
  - Recettes : `/admin/recipes`
- Légal : `/mentions-legales`, `/politique-de-confidentialite`, `/cookies`

---

## Sécurité
- **SQLi** : PDO + requêtes **préparées** partout.
- **XSS** : Twig **auto-escape** ; commentaires affichés via `|e|nl2br`.
- **CSRF** : token sur tous les **POST**.
- **Auth** : `password_hash()` / `password_verify()`, rôles `user`/`admin`.
- **Uploads** : contrôle **MIME** (finfo), taille ≤ 5 Mo, renommage par **slug**, suppression ancienne image.
- **Rate-limit** : délai min. entre 2 commentaires.
- **Cookies** : uniquement **techniques** (session/CSRF).  
  *Conseils prod* : `display_errors=Off`, cookies `httponly` (+ `secure` sous HTTPS), permissions strictes sur `public/uploads`.

---

## Accessibilité & responsive
- Structure sémantique (titres, labels).
- Contrastes et focus visibles.
- **Menu burger** < 640 px.
- **Sticky footer** (mise en page flex : `<main class="site-main">` + CSS).

---

## Pages légales & RGPD
- **Mentions légales**, **Politique de confidentialité**, **Cookies** accessibles depuis le **footer**.
- Cookies **techniques uniquement** → **pas de bannière** de consentement (CNIL).

---

## Déploiement (Apache / o2switch)
1. Uploader les fichiers (ou déployer depuis Git).
2. `composer install --no-dev` sur le serveur.
3. Créer la base + **importer** le schéma/données.
4. Adapter `config/db.php` (DSN/USER/PASS).
5. VHost/DocumentRoot **vers `/public`** (ou `RewriteBase` correct si sous-dossier).
6. Droits d’écriture sur `public/uploads/recipes/`.
7. Tester : `/`, `/recipes`, `/admin`.

---

## Dépannage (FAQ)
- **404 sur toutes les pages**  
  → Vérifier `.htaccess` et `mod_rewrite`. Adapter `RewriteBase`.
- **Erreur “Column not found: created_at”**  
  → Schéma non à jour : exécuter les `ALTER/CREATE TABLE` récents (voir `database/schema.sql`).
- **Upload refusé**  
  → Vérifier extension PHP `fileinfo`, limite taille (`post_max_size`/`upload_max_filesize`), droits dossier `public/uploads/recipes`.
- **Redirection vers /login en admin**  
  → Le compte n’a pas le rôle `admin`. Exécuter l’UPDATE SQL ci-dessus.
- **Slug en conflit**  
  → Le contrôleur génère un **slug unique** automatiquement (suffixes `-2`, `-3`, …). Renommer le titre si besoin.

---

## Roadmap
- Modération des commentaires (`approved/pending`) + interface admin.
- Notifications mail à nouveau commentaire.
- Miniatures d’images (génération server-side).
- Export/Import de recettes (CSV/JSON).
- API lecture (REST) + front SPA éventuel.

---

## Crédits & licence
- Auteur : **Guillaume Agard** — projet pédagogique (TP DWWM).
- Thème : **Les Délices de Kelly** (végé/vegan).
- Images : https://www.pexels.com/fr-fr/ -- crédits individuels si nécessaires.
- Contact : `guillaumeagard.dev@gmail.com`
- Licence : **MIT**

---

