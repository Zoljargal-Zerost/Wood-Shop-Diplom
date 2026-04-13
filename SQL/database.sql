-- ============================================================
--  Модны Зах — Бүрэн Database
--  phpMyAdmin дээр шинэ database үүсгээд энийг Import хийнэ
--  Database нэр: modni_zah
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
--  1. ROLES — динамик role систем
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100)  NOT NULL,              -- "Модны ажилтан"
  slug         VARCHAR(50)   NOT NULL UNIQUE,       -- "wood_worker"
  permissions  JSON          NOT NULL,              -- ["view_orders","update_status"]
  color        VARCHAR(7)    DEFAULT '#5C3D1E',     -- badge өнгө
  is_system    TINYINT(1)    DEFAULT 0,             -- 1=устгаж болохгүй
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
  role_id      INT           NOT NULL DEFAULT 5,   -- default: user
  verified     TINYINT(1)    DEFAULT 0,
  is_active    TINYINT(1)    DEFAULT 1,
  notes        TEXT          DEFAULT NULL,          -- admin тайлбар
  created_at   DATETIME      DEFAULT NOW(),
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- ------------------------------------------------------------
--  3. WORKER PROFILES — role=worker хэрэглэгчийн нэмэлт мэдээлэл
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_profiles (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT           NOT NULL UNIQUE,
  job_title    VARCHAR(100)  DEFAULT NULL,   -- "Модны ажилтан"
  department   VARCHAR(100)  DEFAULT NULL,   -- "Борлуулалт"
  notes        TEXT          DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
--  4. DRIVER PROFILES — role=driver хэрэглэгчийн нэмэлт мэдээлэл
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS driver_profiles (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT           NOT NULL UNIQUE,
  vehicle_plate    VARCHAR(20)   NOT NULL,    -- УНА-1234
  vehicle_model    VARCHAR(100)  DEFAULT NULL, -- Mitsubishi Canter
  vehicle_type     VARCHAR(50)   DEFAULT NULL, -- Ачааны машин
  license_no       VARCHAR(50)   DEFAULT NULL,
  license_expiry   DATE          DEFAULT NULL,
  emergency_phone  VARCHAR(20)   DEFAULT NULL,
  notes            TEXT          DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
--  5. ORDERS — захиалга
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT           NOT NULL,
  worker_id    INT           DEFAULT NULL,   -- хариуцсан worker
  driver_id    INT           DEFAULT NULL,   -- хариуцсан driver
  product      VARCHAR(100)  NOT NULL,
  shirheg      INT           DEFAULT NULL,
  urt_m        DECIMAL(6,2)  DEFAULT NULL,
  urgun_cm     DECIMAL(6,2)  DEFAULT NULL,
  zuzaan_cm    DECIMAL(6,2)  DEFAULT NULL,
  notes        TEXT          DEFAULT NULL,
  status       ENUM('pending','confirmed','processing','delivering','delivered','cancelled')
                             DEFAULT 'pending',
  admin_notes  TEXT          DEFAULT NULL,   -- admin дотоод тайлбар
  created_at   DATETIME      DEFAULT NOW(),
  updated_at   DATETIME      DEFAULT NOW() ON UPDATE NOW(),
  FOREIGN KEY (user_id)   REFERENCES users(id),
  FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ------------------------------------------------------------
--  6. WORKER LOGS — хэн, юу хийсэн бүртгэл
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_logs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  worker_id    INT           NOT NULL,
  order_id     INT           DEFAULT NULL,
  action       VARCHAR(200)  NOT NULL,   -- "Статус: pending→confirmed"
  ip_address   VARCHAR(45)   DEFAULT NULL,
  created_at   DATETIME      DEFAULT NOW(),
  FOREIGN KEY (worker_id) REFERENCES users(id),
  FOREIGN KEY (order_id)  REFERENCES orders(id) ON DELETE SET NULL
);

-- ============================================================
--  SEED DATA — анхдагч role-ууд
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
--  АНХДАГЧ ADMIN ХЭРЭГЛЭГЧ
--  Нэвтрэх: admin@woodshop.mn / Admin1234
--  ЗААВАЛ нууц үгийг солино уу!
-- ============================================================
INSERT INTO users (ner, email, phone, password, role_id, verified, is_active)
VALUES (
  'Админ',
  'admin@woodshop.mn',
  '+97699000000',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin1234
  1,
  1,
  1
);

-- admin (Admin@1234)
UPDATE users SET password = '$2y$10$AiN2x3xRCozQirCXUVZsSeFx0qcobNvZtOgqMtktqR5ACMKxYIzK2'
WHERE email = 'admin@woodshop.mn';

-- director (Director@1234)
UPDATE users SET password = '$2y$10$lZyvx92aGn4EOJHsVMLLgeuc/norSdBidrJjqT9V8jhM2G..Q3toa'
WHERE email = 'director@woodshop.mn';

select * from users;

SET FOREIGN_KEY_CHECKS = 1;
