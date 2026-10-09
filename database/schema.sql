-- Maura Laundry Database Schema
-- db_maura_laundry

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

CREATE DATABASE IF NOT EXISTS `db_maura_laundry` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_maura_laundry`;

-- Roles
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Role Permissions
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_token` varchar(100) DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `u_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customers
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `c_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Services
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('kiloan','satuan','express') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'kg',
  `duration_days` int(11) NOT NULL DEFAULT 3,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recurring order definitions
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `frequency` enum('weekly','biweekly','monthly') NOT NULL,
  `next_due` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `service_type` enum('none','pickup','delivery','both') NOT NULL DEFAULT 'none',
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subscriptions_due` (`is_active`,`next_due`),
  KEY `subscription_customer_id` (`customer_id`),
  CONSTRAINT `s_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `subscription_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL CHECK (`quantity` > 0),
  PRIMARY KEY (`id`),
  KEY `subscription_item_service_id` (`service_id`),
  UNIQUE KEY `subscription_service` (`subscription_id`,`service_id`),
  CONSTRAINT `si_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `si_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Orders
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(20) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('diterima','dicuci','disetrika','selesai','diambil') NOT NULL DEFAULT 'diterima',
  `service_type` enum('none','pickup','delivery','both') NOT NULL DEFAULT 'none',
  `pickup_address` text DEFAULT NULL,
  `pickup_contact` varchar(100) DEFAULT NULL,
  `pickup_fee` decimal(10,2) NOT NULL DEFAULT 0 CHECK (pickup_fee >= 0),
  `pickup_scheduled_at` datetime DEFAULT NULL,
  `pickup_status` enum('not_required','scheduled','on_the_way','picked_up','cancelled') NOT NULL DEFAULT 'not_required',
  `delivery_address` text DEFAULT NULL,
  `delivery_contact` varchar(100) DEFAULT NULL,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0 CHECK (delivery_fee >= 0),
  `delivery_scheduled_at` datetime DEFAULT NULL,
  `delivery_status` enum('not_required','scheduled','on_the_way','delivered','cancelled') NOT NULL DEFAULT 'not_required',
  `notes` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0,
  `estimated_done` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `customer_id` (`customer_id`),
  KEY `user_id` (`user_id`),
  KEY `subscription_id` (`subscription_id`),
  CONSTRAINT `o_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `o_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `o_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Order Items
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oi_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customer deposit ledger (balance is always SUM(amount); rows are immutable)
CREATE TABLE `customer_deposit_transactions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('topup','debit','refund') NOT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT 'Signed: credit positive, debit negative',
  `method` enum('cash','transfer','internal') NOT NULL DEFAULT 'cash',
  `reference` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_deposit_customer_created` (`customer_id`,`created_at`),
  KEY `deposit_order_id` (`order_id`),
  KEY `deposit_user_id` (`user_id`),
  CONSTRAINT `dt_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `dt_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `dt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_deposit_amount` CHECK ((`type`='debit' AND `amount` < 0) OR (`type` IN ('topup','refund') AND `amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('tunai','transfer','deposit') NOT NULL DEFAULT 'tunai',
  `deposit_transaction_id` bigint(20) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  UNIQUE KEY `deposit_transaction_id` (`deposit_transaction_id`),
  CONSTRAINT `p_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `p_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `p_deposit_transaction` FOREIGN KEY (`deposit_transaction_id`) REFERENCES `customer_deposit_transactions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Indexes ──────────────────────────────────────────────────────────────────
ALTER TABLE `orders`
  ADD INDEX `idx_orders_status` (`status`);

ALTER TABLE `payments`
  ADD INDEX `idx_payments_paid_at` (`paid_at`);

-- Seed: Roles
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Super Admin', 'Akses penuh ke seluruh sistem'),
(2, 'Admin',       'Akses manajemen data dan laporan'),
(3, 'Kasir',       'Akses order dan pembayaran'),
(4, 'Operator',    'Akses order dan tracking status'),
(5, 'Pelanggan',   'Akses portal pelanggan untuk tracking order sendiri');

-- Seed: Permissions
INSERT INTO `permissions` (`id`, `name`, `description`) VALUES
(1,  'dashboard.view',    'Lihat dashboard'),
(2,  'orders.view',       'Lihat order'),
(3,  'orders.create',     'Buat order baru'),
(4,  'orders.edit',       'Edit order'),
(5,  'orders.delete',     'Hapus order'),
(6,  'orders.status',     'Update status order'),
(7,  'customers.view',    'Lihat pelanggan'),
(8,  'customers.create',  'Tambah pelanggan'),
(9,  'customers.edit',    'Edit pelanggan'),
(10, 'customers.delete',  'Hapus pelanggan'),
(11, 'services.view',     'Lihat layanan'),
(12, 'services.create',   'Tambah layanan'),
(13, 'services.edit',     'Edit layanan'),
(14, 'services.delete',   'Hapus layanan'),
(15, 'payments.view',     'Lihat pembayaran'),
(16, 'payments.create',   'Catat pembayaran'),
(17, 'reports.view',      'Lihat laporan'),
(18, 'users.view',        'Lihat pengguna'),
(19, 'users.manage',      'Kelola pengguna'),
(20, 'roles.manage',      'Kelola roles'),
(21, 'deposits.view',     'Lihat saldo dan mutasi deposit'),
(22, 'deposits.manage',   'Tambah, debit, dan refund deposit'),
(23, 'subscriptions.view',   'Lihat langganan'),
(24, 'subscriptions.manage', 'Kelola langganan (buat, edit, hapus, generate order)');

-- Super Admin: all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Admin: all except users/roles manage
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),(2,9),(2,10),
(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),(2,17),(2,18),(2,21),(2,22),(2,23),(2,24);

-- Kasir: dashboard, orders, customers view/create, payments, deposits
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3,1),(3,2),(3,3),(3,7),(3,8),(3,15),(3,16),(3,21),(3,22),(3,23),(3,24);

-- Operator: dashboard, orders view/status, customers view
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(4,1),(4,2),(4,6),(4,7);

-- Pelanggan: portal only (no dashboard/orders access)
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(5,1);

-- Seed: Default Super Admin user (password: P@ssw0rd)
INSERT INTO `users` (`role_id`, `name`, `username`, `email`, `password`, `email_verified`) VALUES
(1, 'Super Administrator', 'superadmin', 'admin@mauralaundry.com',
 '$2y$12$oucBlvl6RGkxgjQ0iAF2IehCiJI2tn9epJCvpnW/A0BBFcRKnbTfu', 1);

-- Customer loyalty points
CREATE TABLE `customer_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_id` (`customer_id`),
  CONSTRAINT `cp_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Points transaction history
CREATE TABLE `customer_point_transactions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('earn','redeem') NOT NULL,
  `points` int(11) NOT NULL COMMENT 'Positive for earn, negative for redeem',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cpt_customer` (`customer_id`,`created_at`),
  KEY `cpt_payment` (`payment_id`),
  KEY `cpt_user` (`user_id`),
  CONSTRAINT `cpt_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpt_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cpt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: Sample services
INSERT INTO `services` (`name`, `type`, `price`, `unit`, `duration_days`, `description`) VALUES
('Cuci Kiloan Regular',  'kiloan',  7000,  'kg',   3, 'Cuci + kering standar per kilogram'),
('Cuci Kiloan Premium',  'kiloan',  12000, 'kg',   2, 'Cuci + kering + setrika per kilogram'),
('Express 1 Hari',       'express', 15000, 'kg',   1, 'Cuci + kering + setrika, selesai 1 hari'),
('Cuci Satuan Kemeja',   'satuan',  8000,  'pcs',  3, 'Kemeja/baju formal per pcs'),
('Cuci Satuan Celana',   'satuan',  8000,  'pcs',  3, 'Celana panjang/pendek per pcs'),
('Cuci Satuan Jaket',    'satuan',  15000, 'pcs',  3, 'Jaket/blazer per pcs'),
('Cuci Satuan Sepatu',   'satuan',  25000, 'pcs',  3, 'Sepatu per pasang'),
('Cuci Satuan Selimut',  'satuan',  20000, 'pcs',  4, 'Selimut/bed cover per pcs');
