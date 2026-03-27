-- AutoTrackPlus Database Schema (Fixed)
-- Run this in phpMyAdmin > SQL tab

CREATE DATABASE IF NOT EXISTS autotrackplus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE autotrackplus;

-- Users (employee or admin)
CREATE TABLE IF NOT EXISTS users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  first_name   VARCHAR(80)  NOT NULL,
  last_name    VARCHAR(80)  NOT NULL,
  email        VARCHAR(160) NOT NULL,
  emp_code     CHAR(6)      NOT NULL,
  role         ENUM('employee','admin') NOT NULL DEFAULT 'employee',
  is_verified  TINYINT(1)   NOT NULL DEFAULT 0,
  verify_token CHAR(36)     DEFAULT NULL,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email(email),
  UNIQUE KEY uq_emp(emp_code),
  UNIQUE KEY uq_name_emp (first_name, last_name, emp_code)
);

-- Vehicles
CREATE TABLE IF NOT EXISTS vehicles (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT          NOT NULL,
  make_model       VARCHAR(120) NOT NULL,
  year             INT          NOT NULL,
  plate            VARCHAR(20)  NOT NULL,
  next_service     DATE         NULL,   -- FIX: was NOT NULL, causes errors if left blank
  insurance_expiry DATE         NULL,   -- FIX: was NOT NULL, causes errors if left blank
  mileage          INT          NOT NULL DEFAULT 0,
  miles_left       INT          NOT NULL DEFAULT 0,
  status           ENUM('Due Soon','Expired','Completed') NOT NULL DEFAULT 'Due Soon',
  created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Repairs
CREATE TABLE IF NOT EXISTS repairs (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT          NOT NULL,
  note       VARCHAR(255) NOT NULL,
  tag        ENUM('Due Soon','Expired','Completed') NOT NULL DEFAULT 'Due Soon',
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Notifications (FIX: this table was missing entirely)
CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT         NOT NULL,
  vehicle_id INT         NOT NULL,
  type       ENUM('service','insurance') NOT NULL,
  due_on     DATE        NOT NULL,
  read_at    DATETIME    DEFAULT NULL,
  created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notif (user_id, vehicle_id, type, due_on),  -- prevents duplicate reminders
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Activity Log (FIX: was missing, so history.php used fake data)
CREATE TABLE IF NOT EXISTS activity_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT          NOT NULL,
  action     VARCHAR(255) NOT NULL,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed default admin (change email + code as needed)
INSERT INTO users (first_name, last_name, email, emp_code, role, is_verified)
VALUES ('System', 'Admin', 'admin@example.com', '999999', 'admin', 1)
ON DUPLICATE KEY UPDATE email = email;
