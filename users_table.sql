create database modni_zah;
use modni_zah;
-- Хэрэглэгчийн хүснэгт
-- phpMyAdmin эсвэл MySQL-д ажиллуулна уу

CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  ner        VARCHAR(100)  NOT NULL,
  email      VARCHAR(150)  NOT NULL UNIQUE,
  phone      VARCHAR(20)   NOT NULL,
  password   VARCHAR(255)  NOT NULL,
  verified   TINYINT(1)    NOT NULL DEFAULT 0,
  created_at DATETIME      NOT NULL DEFAULT NOW()
);

select * from users;
drop table users;
DELETE FROM Users WHERE id = 1;