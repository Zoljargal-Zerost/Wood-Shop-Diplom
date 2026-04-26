-- ============================================================
--  Модны Зах — Үндсэн Database
--  Database нэр: modni_zah
--  1. database.sql
--  2. patch_director.sql
--  3. patch_products_v2.sql
--  4. patch_order_items.sql
--  5. patch_chat.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
--  1. ROLES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100)  NOT NULL,
  slug         VARCHAR(50)   NOT NULL UNIQUE,
  permissions  JSON          NOT NULL,
  color        VARCHAR(7)    DEFAULT '#5C3D1E',
  is_system    TINYINT(1)    DEFAULT 0,
  created_by   INT           DEFAULT NULL,
  created_at   DATETIME      DEFAULT NOW()
);

-- ------------------------------------------------------------
--  2. USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ner          VARCHAR(100)  NOT NULL,
  email        VARCHAR(150)  NOT NULL UNIQUE,
  phone        VARCHAR(20)   NOT NULL,
  password     VARCHAR(255)  NOT NULL,
  role_id      INT           NOT NULL DEFAULT 5,
  verified     TINYINT(1)    DEFAULT 0,
  is_active    TINYINT(1)    DEFAULT 1,
  notes        TEXT          DEFAULT NULL,
  created_at   DATETIME      DEFAULT NOW(),
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- ------------------------------------------------------------
--  3. WORKER PROFILES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_profiles (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT           NOT NULL UNIQUE,
  job_title    VARCHAR(100)  DEFAULT NULL,
  department   VARCHAR(100)  DEFAULT NULL,
  notes        TEXT          DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
--  4. DRIVER PROFILES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS driver_profiles (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT           NOT NULL UNIQUE,
  vehicle_plate    VARCHAR(20)   NOT NULL,
  vehicle_model    VARCHAR(100)  DEFAULT NULL,
  vehicle_type     VARCHAR(50)   DEFAULT NULL,
  license_no       VARCHAR(50)   DEFAULT NULL,
  license_expiry   DATE          DEFAULT NULL,
  emergency_phone  VARCHAR(20)   DEFAULT NULL,
  notes            TEXT          DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
--  5. PRODUCTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100)   NOT NULL,
  type        VARCHAR(50)    NOT NULL,
  emoji       VARCHAR(10)    DEFAULT '🪵',
  description TEXT           DEFAULT NULL,
  price_label VARCHAR(100)   DEFAULT 'Үнийн санал авах',
  price_value DECIMAL(12,2)  DEFAULT NULL,
  image_path  VARCHAR(255)   DEFAULT NULL,
  stock       INT            DEFAULT NULL,
  is_active   TINYINT(1)     DEFAULT 1,
  sort_order  INT            DEFAULT 0,
  created_by  INT            DEFAULT NULL,
  created_at  DATETIME       DEFAULT NOW(),
  updated_at  DATETIME       DEFAULT NOW() ON UPDATE NOW()
);

-- ------------------------------------------------------------
--  6. ORDERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT           NOT NULL,
  worker_id    INT           DEFAULT NULL,
  driver_id    INT           DEFAULT NULL,
  product      VARCHAR(100)  NOT NULL,
  shirheg      INT           DEFAULT NULL,
  urt_m        DECIMAL(6,2)  DEFAULT NULL,
  urgun_cm     DECIMAL(6,2)  DEFAULT NULL,
  zuzaan_cm    DECIMAL(6,2)  DEFAULT NULL,
  notes        TEXT          DEFAULT NULL,
  status       ENUM('pending','confirmed','processing','delivering','delivered','cancelled') DEFAULT 'pending',
  admin_notes  TEXT          DEFAULT NULL,
  created_at   DATETIME      DEFAULT NOW(),
  updated_at   DATETIME      DEFAULT NOW() ON UPDATE NOW(),
  FOREIGN KEY (user_id)   REFERENCES users(id),
  FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ------------------------------------------------------------
--  7. WORKER LOGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_logs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  worker_id    INT           NOT NULL,
  order_id     INT           DEFAULT NULL,
  action       VARCHAR(200)  NOT NULL,
  ip_address   VARCHAR(45)   DEFAULT NULL,
  created_at   DATETIME      DEFAULT NOW(),
  FOREIGN KEY (worker_id) REFERENCES users(id),
  FOREIGN KEY (order_id)  REFERENCES orders(id) ON DELETE SET NULL
);

-- ============================================================
--  SEED — Role-ууд
-- ============================================================
INSERT INTO roles (name, slug, permissions, color, is_system) VALUES
('Админ', 'admin', JSON_ARRAY(
  'view_all_users','edit_users','delete_users','manage_roles',
  'view_all_orders','update_order_status','assign_worker','assign_driver',
  'view_worker_logs','manage_workers','manage_drivers',
  'register_customer','view_statistics','view_driver_info','admin_notes'
), '#C0392B', 1),

('Менежер', 'manager', JSON_ARRAY(
  'view_all_users','edit_users',
  'view_all_orders','update_order_status','assign_worker','assign_driver',
  'view_worker_logs','manage_workers','manage_drivers',
  'register_customer','view_statistics','view_driver_info'
), '#8E44AD', 1),

('Ажилтан', 'worker', JSON_ARRAY(
  'view_assigned_orders','update_order_status',
  'register_customer','view_own_customers'
), '#2980B9', 1),

('Жолооч', 'driver', JSON_ARRAY(
  'view_assigned_orders','update_order_status'
), '#27AE60', 1),

('Хэрэглэгч', 'user', JSON_ARRAY(
  'view_own_orders','edit_own_profile'
), '#7F8C8D', 1);

-- ============================================================
--  SEED — Анхдагч Admin хэрэглэгч
--  Нэвтрэх: admin@woodshop.mn / Admin@1234
-- ============================================================
INSERT INTO users (ner, email, phone, password, role_id, verified, is_active)
VALUES (
  'Админ',
  'admin@woodshop.mn',
  '+97699000000',
  '$2y$10$AiN2x3xRCozQirCXUVZsSeFx0qcobNvZtOgqMtktqR5ACMKxYIzK2',
  1, 1, 1
);

SET FOREIGN_KEY_CHECKS = 1;
