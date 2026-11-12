-- ============================================================================
-- PURPLE LOTUS HOTEL MANAGEMENT SYSTEM - DATABASE SCHEMA & SEED DATA
-- Compatible with MySQL 8.0 / MariaDB 10.4+ (XAMPP / phpMyAdmin)
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET CHARACTER SET utf8mb4;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

CREATE DATABASE IF NOT EXISTS `lumina_hotel`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `lumina_hotel`;

-- Disable FK checks for clean re-import
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `security_logs`;
DROP TABLE IF EXISTS `support_requests`;
DROP TABLE IF EXISTS `cleaning`;
DROP TABLE IF EXISTS `maintenance`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `service_orders`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `checkin_checkout`;
DROP TABLE IF EXISTS `booking_details`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `room_images`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `room_types`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. users - Tài khoản người dùng
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(25) DEFAULT NULL,
  `role` ENUM('customer', 'staff', 'admin') NOT NULL DEFAULT 'customer',
  `status` ENUM('active', 'locked') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. customers - Thông tin khách hàng
-- ----------------------------------------------------------------------------
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `id_card` VARCHAR(30) DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `nationality` VARCHAR(80) DEFAULT 'Việt Nam',
  `vip_tier` ENUM('standard', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'standard',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_user_id` (`user_id`),
  KEY `idx_customers_id_card` (`id_card`),
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. employees - Thông tin nhân viên
-- ----------------------------------------------------------------------------
CREATE TABLE `employees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `employee_code` VARCHAR(20) NOT NULL,
  `position` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `hire_date` DATE NOT NULL,
  `salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `shift` ENUM('morning', 'afternoon', 'night', 'full') NOT NULL DEFAULT 'full',
  `status` ENUM('active', 'inactive', 'on_leave') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employees_user_id` (`user_id`),
  UNIQUE KEY `uq_employees_code` (`employee_code`),
  KEY `idx_employees_department` (`department`),
  CONSTRAINT `fk_employees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. room_types - Loại phòng
-- ----------------------------------------------------------------------------
CREATE TABLE `room_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `base_price` DECIMAL(12,2) NOT NULL,
  `capacity` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `bed_type` VARCHAR(100) NOT NULL DEFAULT '1 Giường King',
  `area_sqm` DECIMAL(6,1) NOT NULL DEFAULT 32.0,
  `description` TEXT DEFAULT NULL,
  `amenities` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_types_name` (`name`),
  UNIQUE KEY `uq_room_types_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. rooms - Thông tin phòng
-- ----------------------------------------------------------------------------
CREATE TABLE `rooms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_number` VARCHAR(20) NOT NULL,
  `floor` TINYINT UNSIGNED NOT NULL,
  `room_type_id` INT UNSIGNED NOT NULL,
  `price_per_night` DECIMAL(12,2) NOT NULL,
  `status` ENUM('available', 'reserved', 'occupied', 'cleaning', 'maintenance') NOT NULL DEFAULT 'available',
  `description` TEXT DEFAULT NULL,
  `amenities` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rooms_number` (`room_number`),
  KEY `idx_rooms_status` (`status`),
  KEY `idx_rooms_type` (`room_type_id`),
  CONSTRAINT `fk_rooms_type` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. room_images - Hình ảnh phòng
-- ----------------------------------------------------------------------------
CREATE TABLE `room_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room_images_room` (`room_id`, `is_primary`),
  CONSTRAINT `fk_room_images_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. bookings - Thông tin đặt phòng
-- ----------------------------------------------------------------------------
CREATE TABLE `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_code` VARCHAR(30) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `check_in_date` DATE NOT NULL,
  `check_out_date` DATE NOT NULL,
  `num_guests` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `total_room_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_service_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending',
  `special_requests` TEXT DEFAULT NULL,
  `confirmed_by` INT UNSIGNED DEFAULT NULL,
  `cancelled_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookings_code` (`booking_code`),
  KEY `idx_bookings_customer` (`customer_id`),
  KEY `idx_bookings_dates_status` (`check_in_date`, `check_out_date`, `status`),
  CONSTRAINT `fk_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. booking_details - Chi tiết đặt phòng
-- ----------------------------------------------------------------------------
CREATE TABLE `booking_details` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `room_id` INT UNSIGNED NOT NULL,
  `price_per_night` DECIMAL(12,2) NOT NULL,
  `nights` INT UNSIGNED NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_details_booking` (`booking_id`),
  KEY `idx_booking_details_room` (`room_id`),
  CONSTRAINT `fk_booking_details_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_details_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. checkin_checkout - Thông tin nhận và trả phòng
-- ----------------------------------------------------------------------------
CREATE TABLE `checkin_checkout` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `checked_in_at` DATETIME DEFAULT NULL,
  `checked_in_by` INT UNSIGNED DEFAULT NULL,
  `checked_out_at` DATETIME DEFAULT NULL,
  `checked_out_by` INT UNSIGNED DEFAULT NULL,
  `id_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `deposit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_checkin_booking` (`booking_id`),
  CONSTRAINT `fk_checkin_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_checkin_by` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_checkout_by` FOREIGN KEY (`checked_out_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. services - Danh sách dịch vụ
-- ----------------------------------------------------------------------------
CREATE TABLE `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `category` ENUM('dining', 'laundry', 'spa', 'transport', 'other') NOT NULL DEFAULT 'other',
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `unit` VARCHAR(40) NOT NULL DEFAULT 'Lần',
  `image_path` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_services_category_active` (`category`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. service_orders - Yêu cầu sử dụng dịch vụ
-- ----------------------------------------------------------------------------
CREATE TABLE `service_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `total_price` DECIMAL(12,2) NOT NULL,
  `requested_time` DATETIME DEFAULT NULL,
  `status` ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `handled_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_orders_booking` (`booking_id`),
  KEY `idx_service_orders_customer` (`customer_id`),
  KEY `idx_service_orders_status` (`status`),
  CONSTRAINT `fk_service_orders_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_service_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_service_orders_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_service_orders_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. payments - Thông tin thanh toán
-- ----------------------------------------------------------------------------
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_code` VARCHAR(30) NOT NULL,
  `booking_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('cash', 'bank_transfer') NOT NULL DEFAULT 'cash',
  `payment_type` ENUM('room', 'service', 'full', 'deposit') NOT NULL DEFAULT 'full',
  `status` ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `transaction_ref` VARCHAR(100) DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `confirmed_by` INT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_code` (`payment_code`),
  KEY `idx_payments_booking` (`booking_id`),
  KEY `idx_payments_status_paid_at` (`status`, `paid_at`),
  CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 13. expenses - Chi phí khách sạn
-- ----------------------------------------------------------------------------
CREATE TABLE `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL,
  `category` ENUM('utilities', 'maintenance', 'supplies', 'salary', 'marketing', 'other') NOT NULL DEFAULT 'other',
  `amount` DECIMAL(12,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `description` TEXT DEFAULT NULL,
  `recorded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expenses_date_category` (`expense_date`, `category`),
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 14. tasks - Công việc nhân sự
-- ----------------------------------------------------------------------------
CREATE TABLE `tasks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `assigned_to` INT UNSIGNED NOT NULL,
  `assigned_by` INT UNSIGNED NOT NULL,
  `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
  `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `due_date` DATE DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_assigned` (`assigned_to`, `status`),
  CONSTRAINT `fk_tasks_employee` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 15. maintenance - Thông tin bảo trì
-- ----------------------------------------------------------------------------
CREATE TABLE `maintenance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` INT UNSIGNED DEFAULT NULL,
  `equipment_name` VARCHAR(150) NOT NULL,
  `issue_description` TEXT NOT NULL,
  `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
  `status` ENUM('reported', 'in_progress', 'resolved', 'cancelled') NOT NULL DEFAULT 'reported',
  `reported_by` INT UNSIGNED NOT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `resolved_at` DATETIME DEFAULT NULL,
  `resolution_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_maintenance_status_room` (`status`, `room_id`),
  CONSTRAINT `fk_maintenance_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_maintenance_reporter` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_maintenance_employee` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 16. cleaning - Thông tin vệ sinh phòng
-- ----------------------------------------------------------------------------
CREATE TABLE `cleaning` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` INT UNSIGNED NOT NULL,
  `booking_id` INT UNSIGNED DEFAULT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `cleaning_type` ENUM('checkout', 'daily', 'deep') NOT NULL DEFAULT 'checkout',
  `status` ENUM('pending', 'in_progress', 'completed') NOT NULL DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cleaning_status_room` (`status`, `room_id`),
  CONSTRAINT `fk_cleaning_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cleaning_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cleaning_employee` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 17. support_requests - Yêu cầu hỗ trợ
-- ----------------------------------------------------------------------------
CREATE TABLE `support_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_code` VARCHAR(30) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `booking_id` INT UNSIGNED DEFAULT NULL,
  `subject` VARCHAR(180) NOT NULL,
  `category` ENUM('inquiry', 'complaint', 'room_issue', 'billing', 'feedback', 'other') NOT NULL DEFAULT 'inquiry',
  `message` TEXT NOT NULL,
  `status` ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
  `staff_response` TEXT DEFAULT NULL,
  `handled_by` INT UNSIGNED DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_ticket_code` (`ticket_code`),
  KEY `idx_support_customer_status` (`customer_id`, `status`),
  CONSTRAINT `fk_support_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_support_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_support_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 18. security_logs - Nhật ký hoạt động & sự cố an ninh
-- ----------------------------------------------------------------------------
CREATE TABLE `security_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(120) NOT NULL,
  `log_type` ENUM('login', 'action', 'incident') NOT NULL DEFAULT 'action',
  `severity` ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'info',
  `status` ENUM('logged', 'investigating', 'resolved') NOT NULL DEFAULT 'logged',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `resolved_by` INT UNSIGNED DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_security_type_severity` (`log_type`, `severity`, `created_at`),
  KEY `idx_security_user` (`user_id`),
  CONSTRAINT `fk_security_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_security_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- Passwords:
--   admin@purplelotushotel.vn        -> Admin@123
--   staff@purplelotushotel.vn        -> Staff@123
--   housekeeping@purplelotushotel.vn -> Staff@123
--   customer@purplelotushotel.vn     -> Customer@123
--   vip@purplelotushotel.vn          -> Customer@123
-- ============================================================================

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role`, `status`, `last_login_at`) VALUES
(1, 'admin_lotus', 'admin@purplelotushotel.vn', '$2y$10$od/UJd9wIIkw6CosCI41JuKxG6K/jbfDk1JLEcz2Kv4Lwig/y0.fC', 'Nguyễn Minh Quân', '0901000001', 'admin', 'active', NOW()),
(2, 'staff_reception', 'staff@purplelotushotel.vn', '$2y$10$RNLDEtmXmt4HvECZAk21ketAimDQ/GX6An6R9k2BMm6HkdPYay/fm', 'Trần Thảo Vy', '0902000002', 'staff', 'active', NOW()),
(3, 'staff_housekeeping', 'housekeeping@purplelotushotel.vn', '$2y$10$RNLDEtmXmt4HvECZAk21ketAimDQ/GX6An6R9k2BMm6HkdPYay/fm', 'Lê Hoàng Nam', '0902000003', 'staff', 'active', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 'khach_an', 'customer@purplelotushotel.vn', '$2y$10$3s5Lc3anYPWNP1dDYp3LouyRbBPlnAwv.2IMZFiNzZeQd3RRZIr3.', 'Phạm Gia An', '0913000004', 'customer', 'active', NOW()),
(5, 'khach_linh', 'vip@purplelotushotel.vn', '$2y$10$3s5Lc3anYPWNP1dDYp3LouyRbBPlnAwv.2IMZFiNzZeQd3RRZIr3.', 'Vũ Khánh Linh', '0913000005', 'customer', 'active', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6, 'khach_khoa', 'khoa.tran@gmail.com', '$2y$10$3s5Lc3anYPWNP1dDYp3LouyRbBPlnAwv.2IMZFiNzZeQd3RRZIr3.', 'Trần Đăng Khoa', '0988112233', 'customer', 'active', DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO `customers` (`id`, `user_id`, `id_card`, `date_of_birth`, `gender`, `address`, `nationality`, `vip_tier`, `notes`) VALUES
(1, 4, '079095001234', '1995-04-12', 'male', '128 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh', 'Việt Nam', 'gold', 'Ưa thích phòng tầng cao, yên tĩnh'),
(2, 5, '001192004567', '1992-09-24', 'female', '45 Tràng Tiền, Hoàn Kiếm, Hà Nội', 'Việt Nam', 'platinum', 'Khách VIP thường xuyên công tác'),
(3, 6, '048090007890', '1990-01-18', 'male', '72 Bạch Đằng, Hải Châu, Đà Nẵng', 'Việt Nam', 'silver', 'Yêu cầu check-in sớm khi có phòng');

INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `position`, `department`, `hire_date`, `salary`, `shift`, `status`) VALUES
(1, 2, 'EMP-001', 'Trưởng ca Lễ tân', 'Tiền sảnh (Front Office)', '2024-03-01', 15500000.00, 'morning', 'active'),
(2, 3, 'EMP-002', 'Chuyên viên Buồng phòng & Kỹ thuật', 'Buồng phòng & Bảo trì', '2024-06-15', 12000000.00, 'full', 'active');

INSERT INTO `room_types` (`id`, `name`, `slug`, `base_price`, `capacity`, `bed_type`, `area_sqm`, `description`, `amenities`) VALUES
(1, 'Deluxe Classic', 'deluxe-classic', 1800000.00, 2, '1 Giường Queen', 34.0, 'Không gian tinh tế với tông kem - vàng champagne ấm áp, cửa sổ kính tràn đón ánh sáng tự nhiên.', 'Wifi tốc độ cao, TV 4K 50 inch, Minibar, Két sắt, Bàn làm việc, Máy sấy tóc'),
(2, 'Premier Ocean View', 'premier-ocean-view', 2600000.00, 2, '1 Giường King', 42.0, 'Phòng hạng sang hướng biển toàn cảnh với ban công riêng và bồn tắm đá cẩm thạch.', 'Ban công riêng, Bồn tắm nằm, Wifi 6, Smart TV 55 inch, Máy pha cà phê Espresso, Minibar cao cấp'),
(3, 'Executive Suite', 'executive-suite', 4200000.00, 3, '1 Giường King + Sofa Bed', 62.0, 'Suite đẳng cấp dành cho doanh nhân và kỳ nghỉ thượng lưu, tách biệt phòng khách và phòng ngủ.', 'Phòng khách riêng, Bồn tắm Jacuzzi, Bàn ăn, Lounge Privilege, Smart TV 65 inch, Ưu tiên Check-in'),
(4, 'Family Grand Suite', 'family-grand-suite', 5400000.00, 4, '2 Giường Queen', 78.0, 'Không gian rộng rãi cho gia đình với 2 phòng ngủ kết nối, khu vực sinh hoạt chung sang trọng.', '2 Phòng ngủ, Bếp mini, 2 Phòng tắm, Ban công Panorama, Smart TV, Bộ tiện nghi trẻ em'),
(5, 'Purple Lotus Presidential Suite', 'presidential-suite', 9800000.00, 4, '1 Giường Super King + 1 King', 135.0, 'Tuyệt tác kiến trúc trên tầng cao nhất với tầm nhìn 270 độ, phòng ăn riêng và dịch vụ quản gia 24/7.', 'Quản gia riêng 24/7, Bồn tắm Jacuzzi ngoài trời, Phòng họp riêng, Bar rượu vang, Đưa đón Limousine');

INSERT INTO `rooms` (`id`, `room_number`, `floor`, `room_type_id`, `price_per_night`, `status`, `description`, `amenities`) VALUES
(1, '101', 1, 1, 1800000.00, 'available', 'Phòng Deluxe Classic tầng 1 gần khu vườn nhiệt đới yên tĩnh.', 'Wifi tốc độ cao, TV 4K 50 inch, Minibar, Két sắt, Bàn làm việc'),
(2, '102', 1, 1, 1800000.00, 'occupied', 'Phòng Deluxe Classic tiêu chuẩn cao cấp, nội thất gỗ óc chó.', 'Wifi tốc độ cao, TV 4K 50 inch, Minibar, Két sắt, Bàn làm việc'),
(3, '103', 1, 1, 1850000.00, 'cleaning', 'Phòng Deluxe Classic góc thoáng đãng hai mặt kính.', 'Wifi tốc độ cao, TV 4K 50 inch, Minibar, Két sắt, Trà thảo mộc'),
(4, '201', 2, 2, 2600000.00, 'reserved', 'Phòng Premier hướng vịnh, ánh nắng ban mai rực rỡ.', 'Ban công riêng, Bồn tắm nằm, Wifi 6, Smart TV 55 inch, Espresso'),
(5, '202', 2, 2, 2600000.00, 'available', 'Phòng Premier thiết kế tối giản chuẩn châu Âu.', 'Ban công riêng, Bồn tắm nằm, Wifi 6, Smart TV 55 inch, Espresso'),
(6, '203', 2, 2, 2750000.00, 'available', 'Phòng Premier góc tầng 2 view hồ bơi vô cực.', 'Ban công rộng, Bồn tắm đá, Smart TV 55 inch, Loa Bluetooth Bose'),
(7, '301', 3, 3, 4200000.00, 'occupied', 'Executive Suite có phòng tiếp khách và quầy bar mini.', 'Phòng khách riêng, Jacuzzi, Lounge Privilege, Smart TV 65 inch'),
(8, '302', 3, 3, 4200000.00, 'available', 'Executive Suite tông xanh navy & champagne sang trọng.', 'Phòng khách riêng, Jacuzzi, Lounge Privilege, Smart TV 65 inch'),
(9, '303', 3, 3, 4300000.00, 'maintenance', 'Executive Suite đang bảo trì nâng cấp hệ thống điều hòa thông minh.', 'Phòng khách riêng, Jacuzzi, Lounge Privilege, Smart TV 65 inch'),
(10, '401', 4, 4, 5400000.00, 'available', 'Family Grand Suite dành cho 4 khách với 2 ban công riêng.', '2 Phòng ngủ, Bếp mini, 2 Phòng tắm, Ban công Panorama'),
(11, '402', 4, 4, 5400000.00, 'reserved', 'Family Grand Suite hướng biển toàn cảnh tầng 4.', '2 Phòng ngủ, Bếp mini, 2 Phòng tắm, Ban công Panorama'),
(12, '501', 5, 5, 9800000.00, 'available', 'Purple Lotus Presidential Suite độc bản trên tầng thượng.', 'Quản gia 24/7, Jacuzzi, Phòng ăn riêng, Bar rượu vang, Limousine');

INSERT INTO `room_images` (`room_id`, `image_path`, `is_primary`, `sort_order`) VALUES
(1, 'assets/images/rooms/phong1.jpg', 1, 1),
(1, 'assets/images/rooms/phong2.jpg', 0, 2),
(2, 'assets/images/rooms/phong2.jpg', 1, 1),
(2, 'assets/images/rooms/phong1.jpg', 0, 2),
(3, 'assets/images/rooms/phong3.jpg', 1, 1),
(3, 'assets/images/rooms/phong1.jpg', 0, 2),
(4, 'assets/images/rooms/phong4.jpg', 1, 1),
(4, 'assets/images/rooms/phong3.jpg', 0, 2),
(5, 'assets/images/rooms/phong5.jpg', 1, 1),
(5, 'assets/images/rooms/phong4.jpg', 0, 2),
(6, 'assets/images/rooms/phong6.jpg', 1, 1),
(6, 'assets/images/rooms/phong5.jpg', 0, 2),
(7, 'assets/images/rooms/phong7.jpg', 1, 1),
(7, 'assets/images/rooms/phong4.jpg', 0, 2),
(8, 'assets/images/rooms/phong4.jpg', 1, 1),
(8, 'assets/images/rooms/phong7.jpg', 0, 2),
(9, 'assets/images/rooms/phong5.jpg', 1, 1),
(9, 'assets/images/rooms/phong6.jpg', 0, 2),
(10, 'assets/images/rooms/phong6.jpg', 1, 1),
(10, 'assets/images/rooms/phong2.jpg', 0, 2),
(11, 'assets/images/rooms/phong3.jpg', 1, 1),
(11, 'assets/images/rooms/phong6.jpg', 0, 2),
(12, 'assets/images/rooms/phong7.jpg', 1, 1),
(12, 'assets/images/rooms/phong5.jpg', 0, 2),
(12, 'assets/images/rooms/phong4.jpg', 0, 3);

INSERT INTO `services` (`id`, `name`, `category`, `description`, `price`, `unit`, `image_path`, `is_active`) VALUES
(1, 'Bữa sáng Champagne tại phòng', 'dining', 'Thực đơn Á - Âu thượng hạng phục vụ tận phòng kèm rượu vang sủi.', 450000.00, 'Suất', 'assets/images/services/amthucnhahang1.jpg', 1),
(2, 'Set Ăn tối Fine Dining Purple Lotus', 'dining', 'Thực đơn 5 món chế biến bởi Bếp trưởng tại nhà hàng hoặc ban công riêng.', 1250000.00, 'Khách', 'assets/images/services/nhahang.jpg', 1),
(3, 'Giặt ủi cao cấp trong ngày', 'laundry', 'Giặt hấp, ủi hơi nước và đóng gói chuẩn khách sạn 5 sao trong 4 giờ.', 180000.00, 'Kg', 'assets/images/services/giatuicaocap.jpg', 1),
(4, 'Trị liệu Spa Đá Nóng 90 phút', 'spa', 'Liệu trình thư giãn toàn thân với tinh dầu hoa sen và đá núi lửa ấm.', 950000.00, 'Liệu trình', 'assets/images/services/spacaocap1.webp', 1),
(5, 'Gói Xông hơi & Chăm sóc da mặt', 'spa', 'Tái tạo năng lượng với thảo dược thiên nhiên và tinh chất collagen.', 780000.00, 'Lượt', 'assets/images/services/spacapcap.webp', 1),
(6, 'Đưa đón Sân bay Mercedes E-Class', 'transport', 'Xe sang đưa đón riêng hai chiều kèm khăn lạnh và nước khoáng.', 650000.00, 'Chuyến', 'assets/images/services/duadonxesang.jpg', 1);

-- Bookings covering past months, today active stays, upcoming confirmed & pending
INSERT INTO `bookings` (`id`, `booking_code`, `customer_id`, `check_in_date`, `check_out_date`, `num_guests`, `total_room_amount`, `total_service_amount`, `total_amount`, `status`, `special_requests`, `confirmed_by`, `created_at`) VALUES
(1, 'LUM-2026-001', 1, DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_SUB(CURDATE(), INTERVAL 42 DAY), 2, 7800000.00, 1400000.00, 9200000.00, 'checked_out', 'Gối lông vũ mềm', 2, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(2, 'LUM-2026-002', 2, DATE_SUB(CURDATE(), INTERVAL 18 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), 2, 12600000.00, 1900000.00, 14500000.00, 'checked_out', 'Phòng tầng cao view biển', 1, DATE_SUB(NOW(), INTERVAL 22 DAY)),
(3, 'LUM-2026-003', 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), CURDATE(), 2, 5550000.00, 450000.00, 6000000.00, 'checked_out', 'Check-out trưa 12h', 2, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 'LUM-2026-004', 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 2, 5400000.00, 1130000.00, 6530000.00, 'checked_in', 'Thêm 2 chai nước suối mỗi ngày', 2, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 'LUM-2026-005', 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 2, 12600000.00, 950000.00, 13550000.00, 'checked_in', 'Chuẩn bị hoa tươi chào mừng', 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(6, 'LUM-2026-006', 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 2, 7800000.00, 0.00, 7800000.00, 'confirmed', 'Check-in lúc 14:00', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 'LUM-2026-007', 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 4, 16200000.00, 0.00, 16200000.00, 'confirmed', 'Gia đình có trẻ nhỏ, cần nôi em bé', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(8, 'LUM-2026-008', 2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), 2, 5200000.00, 0.00, 5200000.00, 'pending', 'Xin báo giá gói đón sân bay', NULL, NOW());

INSERT INTO `booking_details` (`booking_id`, `room_id`, `price_per_night`, `nights`, `subtotal`) VALUES
(1, 5, 2600000.00, 3, 7800000.00),
(2, 8, 4200000.00, 3, 12600000.00),
(3, 3, 1850000.00, 3, 5550000.00),
(4, 2, 1800000.00, 3, 5400000.00),
(5, 7, 4200000.00, 3, 12600000.00),
(6, 4, 2600000.00, 3, 7800000.00),
(7, 11, 5400000.00, 3, 16200000.00),
(8, 6, 2600000.00, 2, 5200000.00);

INSERT INTO `checkin_checkout` (`booking_id`, `checked_in_at`, `checked_in_by`, `checked_out_at`, `checked_out_by`, `id_verified`, `deposit_amount`, `notes`) VALUES
(1, DATE_SUB(NOW(), INTERVAL 45 DAY), 2, DATE_SUB(NOW(), INTERVAL 42 DAY), 2, 1, 2000000.00, 'Hoàn tất lưu trú tốt đẹp'),
(2, DATE_SUB(NOW(), INTERVAL 18 DAY), 2, DATE_SUB(NOW(), INTERVAL 15 DAY), 2, 1, 3000000.00, 'Khách VIP hài lòng'),
(3, DATE_SUB(NOW(), INTERVAL 3 DAY), 2, NOW(), 2, 1, 1500000.00, 'Đã trả phòng, chuyển vệ sinh'),
(4, DATE_SUB(NOW(), INTERVAL 1 DAY), 2, NULL, NULL, 1, 2000000.00, 'Đã kiểm tra CCCD'),
(5, DATE_SUB(NOW(), INTERVAL 2 DAY), 2, NULL, NULL, 1, 4000000.00, 'Khách VIP đã nhận thẻ từ');

INSERT INTO `service_orders` (`booking_id`, `customer_id`, `service_id`, `quantity`, `unit_price`, `total_price`, `requested_time`, `status`, `notes`, `handled_by`) VALUES
(1, 1, 1, 1, 450000.00, 450000.00, DATE_SUB(NOW(), INTERVAL 44 DAY), 'completed', 'Phục vụ 7h30 sáng', 2),
(1, 1, 4, 1, 950000.00, 950000.00, DATE_SUB(NOW(), INTERVAL 43 DAY), 'completed', 'Đặt lịch Spa 16h', 2),
(2, 2, 2, 1, 1250000.00, 1250000.00, DATE_SUB(NOW(), INTERVAL 17 DAY), 'completed', 'Bàn hướng biển', 2),
(2, 2, 6, 1, 650000.00, 650000.00, DATE_SUB(NOW(), INTERVAL 18 DAY), 'completed', 'Đón chuyến VN214', 2),
(3, 3, 1, 1, 450000.00, 450000.00, DATE_SUB(NOW(), INTERVAL 2 DAY), 'completed', 'Không hành', 2),
(4, 1, 1, 1, 450000.00, 450000.00, NOW(), 'confirmed', 'Giao lúc 8h00 sáng mai', 2),
(4, 1, 3, 2, 180000.00, 360000.00, NOW(), 'in_progress', '2 áo sơ mi & 1 quần tây', 3),
(4, 1, 3, 1, 320000.00, 320000.00, NOW(), 'pending', 'Ủi gấp vest dự hội nghị', NULL),
(5, 2, 4, 1, 950000.00, 950000.00, NOW(), 'confirmed', 'Đặt lịch lúc 18h00', 2);

INSERT INTO `payments` (`payment_code`, `booking_id`, `customer_id`, `amount`, `payment_method`, `payment_type`, `status`, `transaction_ref`, `paid_at`, `confirmed_by`, `notes`) VALUES
('PAY-2026-001', 1, 1, 9200000.00, 'bank_transfer', 'full', 'completed', 'VCB-99102831', DATE_SUB(NOW(), INTERVAL 42 DAY), 2, 'Đã thanh toán đầy đủ khi check-out'),
('PAY-2026-002', 2, 2, 14500000.00, 'bank_transfer', 'full', 'completed', 'TCB-77451209', DATE_SUB(NOW(), INTERVAL 15 DAY), 1, 'Chuyển khoản doanh nghiệp'),
('PAY-2026-003', 3, 3, 6000000.00, 'cash', 'full', 'completed', 'CASH-003', NOW(), 2, 'Thu tiền mặt tại quầy lễ tân'),
('PAY-2026-004', 4, 1, 2000000.00, 'bank_transfer', 'deposit', 'completed', 'MB-88321045', DATE_SUB(NOW(), INTERVAL 1 DAY), 2, 'Đặt cọc khi nhận phòng'),
('PAY-2026-005', 5, 2, 13550000.00, 'bank_transfer', 'full', 'pending', 'ACB-55201944', NULL, NULL, 'Khách vừa chuyển khoản, chờ lễ tân đối soát');

INSERT INTO `expenses` (`title`, `category`, `amount`, `expense_date`, `description`, `recorded_by`) VALUES
('Hóa đơn điện nước tháng trước', 'utilities', 18500000.00, DATE_SUB(CURDATE(), INTERVAL 25 DAY), 'Tiền điện hệ thống điều hòa trung tâm và nước sinh hoạt', 1),
('Mua vật tư amenities cao cấp', 'supplies', 8200000.00, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'Bộ chăm sóc cá nhân, khăn bông Ai Cập, trà thảo mộc', 1),
('Bảo dưỡng thang máy định kỳ', 'maintenance', 4500000.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Kiểm tra an toàn hệ thống thang máy khách hàng', 1),
('Chi phí quảng cáo mùa du lịch', 'marketing', 6000000.00, CURDATE(), 'Chiến dịch truyền thông nghỉ dưỡng Purple Lotus Luxury', 1);

INSERT INTO `tasks` (`title`, `description`, `assigned_to`, `assigned_by`, `priority`, `status`, `due_date`) VALUES
('Chuẩn bị hoa tươi & thiệp chào mừng phòng 201', 'Khách VIP đặt phòng 201 check-in hôm nay, chuẩn bị hoa lan trắng và trái cây tươi.', 1, 1, 'high', 'in_progress', CURDATE()),
('Tổng vệ sinh & kiểm tra minibar phòng 103', 'Phòng 103 vừa check-out, cần hút bụi, thay ga giường và bổ sung minibar.', 2, 1, 'urgent', 'pending', CURDATE()),
('Kiểm tra định kỳ hệ thống khóa từ tầng 3', 'Rà soát pin và tín hiệu đầu đọc thẻ từ các phòng tầng 3.', 2, 1, 'medium', 'completed', DATE_SUB(CURDATE(), INTERVAL 2 DAY));

INSERT INTO `maintenance` (`room_id`, `equipment_name`, `issue_description`, `priority`, `status`, `reported_by`, `assigned_to`, `cost`, `resolution_notes`) VALUES
(9, 'Điều hòa trung tâm phòng 303', 'Máy lạnh làm mát chậm và có tiếng ồn nhẹ ở dàn lạnh.', 'high', 'in_progress', 2, 2, 1200000.00, 'Đang vệ sinh dàn lạnh và nạp thêm gas'),
(4, 'Đèn ngủ đầu giường bên trái phòng 201', 'Công tắc cảm ứng hơi chập chờn cần thay mới trước giờ check-in.', 'medium', 'resolved', 2, 2, 350000.00, 'Đã thay mới module cảm ứng và kiểm tra an toàn');

INSERT INTO `cleaning` (`room_id`, `booking_id`, `assigned_to`, `cleaning_type`, `status`, `notes`, `started_at`, `completed_at`) VALUES
(3, 3, 2, 'checkout', 'pending', 'Khách vừa trả phòng, cần dọn sạch để sẵn sàng đón khách mới', NULL, NULL),
(2, 4, 2, 'daily', 'completed', 'Dọn phòng hàng ngày theo yêu cầu lúc 10h00', DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR));

INSERT INTO `support_requests` (`ticket_code`, `customer_id`, `booking_id`, `subject`, `category`, `message`, `status`, `staff_response`, `handled_by`, `resolved_at`) VALUES
('SUP-2026-001', 1, 4, 'Yêu cầu thêm gối phụ phòng 102', 'room_issue', 'Nhờ khách sạn hỗ trợ gửi thêm 2 gối bông mềm lên phòng 102 vào buổi tối.', 'resolved', 'Lễ tân đã cử nhân viên buồng phòng mang 2 gối mới lên phòng 102 cho quý khách.', 2, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
('SUP-2026-002', 2, 5, 'Xuất hóa đơn VAT công ty', 'billing', 'Vui lòng hỗ trợ xuất hóa đơn điện tử theo thông tin Công ty Cổ phần Lumina Tech.', 'open', NULL, NULL, NULL);

INSERT INTO `security_logs` (`user_id`, `action`, `log_type`, `severity`, `status`, `ip_address`, `user_agent`, `details`) VALUES
(1, 'Đăng nhập thành công vào hệ thống quản trị', 'login', 'info', 'logged', '127.0.0.1', 'Mozilla/5.0', 'Admin đăng nhập thành công'),
(2, 'Xác nhận Check-in đơn LUM-2026-004 (Phòng 102)', 'action', 'info', 'logged', '127.0.0.1', 'Mozilla/5.0', 'Đã xác minh CCCD và nhận cọc 2.000.000 VNĐ'),
(NULL, 'Đăng nhập thất bại nhiều lần từ IP lạ', 'incident', 'warning', 'investigating', '192.168.1.99', 'Mozilla/5.0', 'Phát hiện 4 lần nhập sai mật khẩu liên tiếp vào tài khoản staff_reception');

COMMIT;
