-- Les Délices de Kelly — schema.sql
-- Minimal database schema for the PHP MVC monolith
-- Engine: InnoDB, Charset: utf8mb4

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Safety drops (optional)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS recipe_category;
DROP TABLE IF EXISTS recipes;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories
CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recipes
CREATE TABLE recipes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(120) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  summary TEXT NOT NULL,
  diet ENUM('vegan','vegetarien') NULL,
  ingredients MEDIUMTEXT NOT NULL,
  steps MEDIUMTEXT NOT NULL,
  tags VARCHAR(255) NULL,
  image VARCHAR(255) NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  prep_minutes INT UNSIGNED NULL,
  cook_minutes INT UNSIGNED NULL,
  servings INT UNSIGNED NULL,
  difficulty VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recipes_slug (slug),
  KEY idx_recipes_created (created_at),
  KEY idx_recipes_published (published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pivot recipe_category
CREATE TABLE recipe_category (
  recipe_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (recipe_id, category_id),
  KEY idx_rc_category (category_id),
  CONSTRAINT fk_rc_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comments
CREATE TABLE comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comments_recipe_created (recipe_id, created_at),
  KEY idx_comments_user_created (user_id, created_at),
  CONSTRAINT fk_comments_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Optional seed data (uncomment & adjust if needed)
-- ------------------------------------------------------------

-- INSERT INTO categories (name, slug) VALUES
--   ('Desserts', 'desserts'),
--   ('Plats', 'plats');

-- Create an admin user (replace HASHED_PASSWORD with output of: 
-- php -r "echo password_hash('admin123', PASSWORD_DEFAULT), PHP_EOL;")
-- INSERT INTO users (name, email, password_hash, role) VALUES
--   ('Admin', 'admin@example.com', 'HASHED_PASSWORD', 'admin');
