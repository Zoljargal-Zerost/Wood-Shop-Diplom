-- ============================================================
--  patch_director.sql — Захирал role нэмэх
--  phpMyAdmin → modni_zah → SQL → Run
--  ⚠️  database.sql-ийн ДАРАА ажиллуулна
-- ============================================================

USE modni_zah;

INSERT INTO roles (name, slug, permissions, color, is_system)
VALUES (
  'Захирал',
  'director',
  JSON_ARRAY(
    'view_all_users','edit_users',
    'view_all_orders','update_order_status','assign_worker','assign_driver',
    'view_worker_logs','manage_workers','manage_drivers',
    'register_customer','view_statistics','view_driver_info','admin_notes',
    'manage_roles','view_salary','view_reports'
  ),
  '#8B0000',
  1
)
ON DUPLICATE KEY UPDATE
  permissions = VALUES(permissions),
  color       = VALUES(color);
