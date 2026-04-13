-- ============================================================
--  PATCH — Захирал role нэмэх + Admin нууц үг засах
--  database.sql-г бүрэн дахин import хийхийн оронд
--  зөвхөн ЭНЭ файлыг phpMyAdmin → Import хийнэ
-- ============================================================

-- ------------------------------------------------------------
--  1. ЗАХИРАЛ role нэмэх
--     (admin-тай бараг адил, гэхдээ is_system=1 тул устгагдахгүй)
-- ------------------------------------------------------------
INSERT INTO roles (name, slug, permissions, color, is_system)
VALUES (
  'Захирал',
  'director',
  JSON_ARRAY(
    'view_all_users', 'edit_users',
    'view_all_orders', 'update_order_status', 'assign_worker', 'assign_driver',
    'view_worker_logs', 'manage_workers', 'manage_drivers',
    'register_customer', 'view_statistics', 'view_driver_info', 'admin_notes',
    'manage_roles',
    'view_salary',        -- ирээдүйд
    'view_reports'        -- ирээдүйд
  ),
  '#8B0000',   -- бараан улаан
  1
)
ON DUPLICATE KEY UPDATE
  permissions = VALUES(permissions),
  color = VALUES(color);
  
-- ------------------------------------------------------------
--  3. Захирал хэрэглэгч үүсгэх (заавал биш, test хийхэд)
--     Нэвтрэх: director@woodshop.mn / Director@1234
-- ------------------------------------------------------------
-- INSERT INTO users (ner, email, phone, password, role_id, verified, is_active)
-- SELECT
--   'Захирал',
--   'director@woodshop.mn',
--   '+97699000001',
--   '$2y$10$TKh8H1.PfkFNZAHBxAF7/OxHFfm9qJPRFjAjZFGGVYp0Yj0ADRH2',
--   (SELECT id FROM roles WHERE slug = 'director'),
--   1, 1;
-- Дээрх мөрийг -- устгаад ажиллуулна.
