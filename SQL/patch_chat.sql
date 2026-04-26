-- ============================================================
--  patch_chat.sql — Чат системийн хүснэгтүүд
--  patch_order_items.sql-ийн ДАРАА ажиллуулна
-- ============================================================

USE modni_zah;

-- 1. CHAT ROOMS
CREATE TABLE IF NOT EXISTS chat_rooms (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  room_type          ENUM('user_worker','internal','order') NOT NULL,
  user_id            INT        DEFAULT NULL,
  worker_id          INT        DEFAULT NULL,
  order_id           INT        DEFAULT NULL,
  deleted_by_user    TINYINT(1) DEFAULT 0,
  archived_by_worker TINYINT(1) DEFAULT 0,
  created_at         DATETIME   DEFAULT NOW(),
  FOREIGN KEY (user_id)   REFERENCES users(id)  ON DELETE CASCADE,
  FOREIGN KEY (worker_id) REFERENCES users(id)  ON DELETE SET NULL,
  FOREIGN KEY (order_id)  REFERENCES orders(id) ON DELETE SET NULL
);

-- 2. CHAT MESSAGES
CREATE TABLE IF NOT EXISTS chat_messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  room_id    INT        NOT NULL,
  sender_id  INT        NOT NULL,
  message    TEXT       NOT NULL,
  is_read    TINYINT(1) DEFAULT 0,
  created_at DATETIME   DEFAULT NOW(),
  FOREIGN KEY (room_id)   REFERENCES chat_rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id)      ON DELETE CASCADE
);

-- 3. USER ONLINE STATUS
CREATE TABLE IF NOT EXISTS user_online (
  user_id   INT      PRIMARY KEY,
  last_seen DATETIME DEFAULT NOW(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_messages_room   ON chat_messages(room_id, created_at);
CREATE INDEX IF NOT EXISTS idx_messages_unread ON chat_messages(room_id, is_read);
CREATE INDEX IF NOT EXISTS idx_online_seen     ON user_online(last_seen);
