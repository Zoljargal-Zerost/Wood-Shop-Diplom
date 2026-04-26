-- ============================================================
--  patch_order_items.sql
--  patch_products_v2.sql-ийн ДАРАА ажиллуулна
-- ============================================================

USE modni_zah;

-- orders хүснэгтэд нийт дүн нэмэх
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS total_price  INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS item_count   INT DEFAULT 0;

-- Захиалгын мөр бүр тусдаа хүснэгтэд
CREATE TABLE IF NOT EXISTS order_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT           NOT NULL,
  product_id    INT           NOT NULL,
  variant_id    INT           NOT NULL,
  product_name  VARCHAR(150)  NOT NULL,   -- "Банз"
  variant_name  VARCHAR(200)  NOT NULL,   -- "5-ийн банз (5×15×4м)"
  sell_type     ENUM('shirheg','kub','bagts','porter') NOT NULL,
  qty           DECIMAL(10,2) NOT NULL,   -- тоо (куб дутуу байж болно)
  unit_price    INT           NOT NULL,   -- нэгжийн үнэ
  subtotal      INT           NOT NULL,   -- дэд нийлбэр
  created_at    DATETIME      DEFAULT NOW(),
  FOREIGN KEY (order_id)   REFERENCES orders(id)            ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)          ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id)  ON DELETE CASCADE
);
