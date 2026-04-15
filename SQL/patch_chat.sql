-- ============================================================
--  patch_chat.sql — Чат системийн хүснэгтүүд
--  phpMyAdmin → modni_zah → SQL → Run
-- ============================================================

USE modni_zah;

-- ------------------------------------------------------------
--  1. CHAT ROOMS — чатын өрөө
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_rooms (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  room_type    ENUM('user_worker','internal','order') NOT NULL,
  user_id      INT           DEFAULT NULL,   -- хэрэглэгч (user_worker)
  worker_id    INT           DEFAULT NULL,   -- ажилтан
  order_id     INT           DEFAULT NULL,   -- захиалгатай холбоотой бол
  created_at   DATETIME      DEFAULT NOW(),
  FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (order_id)  REFERENCES orders(id) ON DELETE SET NULL
);

-- ------------------------------------------------------------
--  2. CHAT MESSAGES — мессежүүд
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_messages (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  room_id      INT           NOT NULL,
  sender_id    INT           NOT NULL,
  message      TEXT          NOT NULL,
  is_read      TINYINT(1)    DEFAULT 0,
  created_at   DATETIME      DEFAULT NOW(),
  FOREIGN KEY (room_id)   REFERENCES chat_rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);



select * from chat_messages;

-- ------------------------------------------------------------
--  3. USER ONLINE STATUS — онлайн статус
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_online (
  user_id      INT           PRIMARY KEY,
  last_seen    DATETIME      DEFAULT NOW(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Index — хурдасгах
CREATE INDEX IF NOT EXISTS idx_messages_room    ON chat_messages(room_id, created_at);
CREATE INDEX IF NOT EXISTS idx_messages_unread  ON chat_messages(room_id, is_read);
CREATE INDEX IF NOT EXISTS idx_online_seen      ON user_online(last_seen);

ALTER TABLE chat_rooms
  DROP COLUMN IF EXISTS hidden_by_user,
  DROP COLUMN IF EXISTS hidden_by_worker,
  ADD COLUMN deleted_by_user   TINYINT(1) DEFAULT 0,   -- хэрэглэгч устгасан (бүрмөсөн)
  ADD COLUMN archived_by_worker TINYINT(1) DEFAULT 0;  -- ажилтан архивд оруулсан