-- ============================================================
--  patch_products_v2.sql
--  phpMyAdmin → modni_zah → SQL → Run
-- ============================================================

USE modni_zah;

-- ------------------------------------------------------------
--  1. product_variants хүснэгт
--     Нэг бүтээгдэхүүн (products) → олон вариант
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_variants (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  product_id    INT           NOT NULL,
  name          VARCHAR(200)  NOT NULL,         -- "5-ийн банз (5×15×4м)"
  zuzaan_cm     DECIMAL(5,1)  DEFAULT NULL,     -- зузаан
  urgun_cm      DECIMAL(5,1)  DEFAULT NULL,     -- өргөн
  urt_m         DECIMAL(5,1)  DEFAULT NULL,     -- урт
  -- Үнэ
  unit_price    INT           DEFAULT NULL,     -- 1 ширхэгийн үнэ
  cube_price    INT           DEFAULT NULL,     -- 1 куб метрийн үнэ
  per_cube      INT           DEFAULT NULL,     -- 1 куб дотор хэдэн ширхэг
  pack_price    INT           DEFAULT NULL,     -- 1 багцын үнэ
  per_pack      INT           DEFAULT NULL,     -- 1 багц дотор хэдэн ширхэг
  porter_price  INT           DEFAULT NULL,     -- 1 портерийн үнэ
  -- Зарах хэлбэрүүд (1=тийм, 0=үгүй)
  sell_shirheg  TINYINT(1)    DEFAULT 0,
  sell_kub      TINYINT(1)    DEFAULT 0,
  sell_bagts    TINYINT(1)    DEFAULT 0,
  sell_porter   TINYINT(1)    DEFAULT 0,
  --
  is_active     TINYINT(1)    DEFAULT 1,
  sort_order    INT           DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
--  2. products хүснэгтийг цэвэрлэж 7 үндсэн төрөл үүсгэх
-- ------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE product_variants;
TRUNCATE TABLE products;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO products (name, type, emoji, description, is_active, sort_order) VALUES
('Гантбол',        'gantbol',  '🪵', 'Нарс мод. Барилга, суурийн тулгуур.',          1, 1),
('Палк',           'palk',     '🌲', 'Нарс мод. Дам нуруу, хаалт, барилга.',         1, 2),
('Банз',           'banz',     '🪵', 'Нарс мод. Шал, хана, тавилга.',                1, 3),
('Брус',           'brus',     '🌲', 'Нарс мод. Хүрээ, тулгуур, хаалт.',             1, 4),
('Зах банз',       'zah_banz', '🪵', 'Нарс мод. Модны захын хольцтой банз.',         1, 5),
('Хаягдал түлээ',  'tvlee',    '🔥', 'Нарс мод. Халаалтад зориулсан хаягдал мод.',   1, 6),
('2-р зах',        'zah2',     '🪵', 'Нарс мод. Хашаа, тааз, өвөлжүүд хэрэглэнэ.',  1, 7);

-- ------------------------------------------------------------
--  3. Вариантуудыг оруулах
-- ------------------------------------------------------------

-- ГАНТБОЛ
INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, sell_shirheg, sort_order)
SELECT id, '30×15 (4м)', 30, 15, 4, 150000, 1, 1 FROM products WHERE type='gantbol';

-- ПАЛК
INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, sell_shirheg, sort_order)
SELECT id, '15×15 (4м)', 15, 15, 4, 75000, 1, 1 FROM products WHERE type='palk';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, sell_shirheg, sort_order)
SELECT id, '7×15 (4м)', 7, 15, 4, 37500, 1, 2 FROM products WHERE type='palk';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, sell_shirheg, sort_order)
SELECT id, '15×15 (6м)', 15, 15, 6, 120000, 1, 3 FROM products WHERE type='palk';

-- БАНЗ
INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, cube_price, per_cube, sell_shirheg, sell_kub, sort_order)
SELECT id, '5-ийн банз (5×15×4м)', 5, 15, 4, 25000, 625000, 25, 1, 1, 1 FROM products WHERE type='banz';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, cube_price, per_cube, sell_shirheg, sell_kub, sort_order)
SELECT id, '4-ийн банз (4×15×4м)', 4, 15, 4, 21000, 630000, 30, 1, 1, 2 FROM products WHERE type='banz';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, cube_price, per_cube, sell_shirheg, sell_kub, sort_order)
SELECT id, '3-ийн банз (3×15×4м)', 3, 15, 4, 15000, 600000, 40, 1, 1, 3 FROM products WHERE type='banz';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, cube_price, per_cube, sell_shirheg, sell_kub, sort_order)
SELECT id, '2-ийн банз (2×15×4м)', 2, 15, 4, 11000, 550000, 50, 1, 1, 4 FROM products WHERE type='banz';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, cube_price, per_cube, sell_shirheg, sell_kub, sort_order)
SELECT id, '3-ийн банз (3×15×2м)', 3, 15, 2, 7000, 560000, 80, 1, 1, 5 FROM products WHERE type='banz';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, cube_price, per_cube, sell_shirheg, sell_kub, sort_order)
SELECT id, '2-ийн банз (2×15×2м)', 2, 15, 2, 5500, 550000, 100, 1, 1, 6 FROM products WHERE type='banz';

-- БРУС
INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, pack_price, per_pack, sell_bagts, sort_order)
SELECT id, '4×4 (4м)', 4, 4, 4, 60000, 9, 1, 1 FROM products WHERE type='brus';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, pack_price, per_pack, sell_shirheg, sell_bagts, sort_order)
SELECT id, '4×7 (4м)', 4, 7, 4, 12000, 72000, 6, 1, 1, 2 FROM products WHERE type='brus';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, pack_price, per_pack, sell_shirheg, sell_bagts, sort_order)
SELECT id, '5×7 (4м)', 5, 7, 4, 13000, 78000, 6, 1, 1, 3 FROM products WHERE type='brus';

INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, pack_price, per_pack, sell_shirheg, sell_bagts, sort_order)
SELECT id, '7×7 (4м)', 7, 7, 4, 18500, 74000, 4, 1, 1, 4 FROM products WHERE type='brus';

-- ЗАХ БАНЗ
INSERT INTO product_variants (product_id, name, porter_price, sell_porter, sort_order)
SELECT id, 'Зах банз (хольцтой)', 500000, 1, 1 FROM products WHERE type='zah_banz';

-- ХАЯГДАЛ ТҮЛЭЭ
INSERT INTO product_variants (product_id, name, porter_price, sell_porter, sort_order)
SELECT id, 'Хаягдал түлээ', 300000, 1, 1 FROM products WHERE type='tvlee';

-- 2-Р ЗАХ
INSERT INTO product_variants (product_id, name, zuzaan_cm, urgun_cm, urt_m, unit_price, cube_price, per_cube, sell_shirheg, sell_kub, sort_order)
SELECT id, '3-ийн зах (3×20-25×2м)', 3, 22, 2, 7500, 450000, 60, 1, 1, 1 FROM products WHERE type='zah2';
