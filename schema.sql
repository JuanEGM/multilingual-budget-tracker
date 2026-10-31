-- ========================================================
-- Gestor de Presupuesto Personal y Control de Gastos
-- Database Schema - Phase 1 + Transacciones Fijas & Suscripciones
-- Compatible with MySQL 5.7+, MySQL 8.x and MariaDB 10.3+
-- ========================================================

-- Create database if it does not exist
CREATE DATABASE IF NOT EXISTS `gestor_financiero` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `gestor_financiero`;

-- --------------------------------------------------------
-- Table structure for `transacciones` (Libro diario de transacciones)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transacciones`;

CREATE TABLE `transacciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo` ENUM('income', 'expense') NOT NULL COMMENT 'Transaction type: income or expense',
    `concepto` VARCHAR(150) NOT NULL COMMENT 'Short description of the transaction',
    `monto` DECIMAL(10, 2) NOT NULL COMMENT 'Transaction amount with 2 decimal places',
    `categoria` VARCHAR(50) NOT NULL COMMENT 'Classification category',
    `fecha` DATE NOT NULL COMMENT 'Date when the transaction occurred',
    `es_fija` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if generated from a recurring subscription or payment',
    `fija_id` INT NULL COMMENT 'Reference to transacciones_fijas if applicable',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Audit timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `transacciones_fijas` (Pagos fijos mensuales y suscripciones)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transacciones_fijas`;

CREATE TABLE `transacciones_fijas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo` ENUM('income', 'expense') NOT NULL COMMENT 'Transaction type: income or expense',
    `concepto` VARCHAR(150) NOT NULL COMMENT 'Subscription / Fixed payment name (e.g. Netflix, Rent, Salary)',
    `monto` DECIMAL(10, 2) NOT NULL COMMENT 'Monthly recurring amount',
    `categoria` VARCHAR(50) NOT NULL COMMENT 'Classification category',
    `dia_pago` TINYINT NOT NULL DEFAULT 1 COMMENT 'Scheduled day of the month (1-31)',
    `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Paused',
    `ultima_generacion` DATE NULL COMMENT 'Last date when applied to transacciones table',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Audit timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Indexes for performance optimization
-- --------------------------------------------------------
CREATE INDEX `idx_transacciones_fecha` ON `transacciones` (`fecha`);
CREATE INDEX `idx_transacciones_tipo` ON `transacciones` (`tipo`);
CREATE INDEX `idx_transacciones_fijas_activo` ON `transacciones_fijas` (`activo`);
CREATE INDEX `idx_transacciones_fijas_dia` ON `transacciones_fijas` (`dia_pago`);

-- --------------------------------------------------------
-- Initial Sample Data: Transacciones Fijas / Suscripciones
-- --------------------------------------------------------
INSERT INTO `transacciones_fijas` (`tipo`, `concepto`, `monto`, `categoria`, `dia_pago`, `activo`, `ultima_generacion`) VALUES
('income', 'Fixed Monthly Salary', 3500.00, 'Salary', 30, 1, DATE_SUB(CURRENT_DATE(), INTERVAL 10 DAY)),
('expense', 'Netflix Premium Plan', 15.99, 'Entertainment', 5, 1, DATE_SUB(CURRENT_DATE(), INTERVAL 8 DAY)),
('expense', 'Spotify Family Plan', 10.99, 'Entertainment', 12, 1, DATE_SUB(CURRENT_DATE(), INTERVAL 8 DAY)),
('expense', 'Apartment Rent', 850.00, 'Housing & Rent', 1, 1, DATE_SUB(CURRENT_DATE(), INTERVAL 10 DAY)),
('expense', 'High-Speed Fiber Internet', 45.00, 'Utilities', 15, 1, DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY)),
('expense', 'Gym Membership', 35.00, 'Health', 20, 1, NULL);

-- --------------------------------------------------------
-- Initial Sample Data: Transacciones del Libro Diario
-- --------------------------------------------------------
INSERT INTO `transacciones` (`tipo`, `concepto`, `monto`, `categoria`, `fecha`, `es_fija`, `fija_id`) VALUES
('income', 'Monthly Salary', 3500.00, 'Salary', DATE_SUB(CURRENT_DATE(), INTERVAL 10 DAY), 1, 1),
('expense', 'Apartment Rent', 850.00, 'Housing & Rent', DATE_SUB(CURRENT_DATE(), INTERVAL 10 DAY), 1, 4),
('expense', 'Supermarket Groceries', 154.20, 'Food', DATE_SUB(CURRENT_DATE(), INTERVAL 8 DAY), 0, NULL),
('expense', 'Netflix Premium Plan', 15.99, 'Entertainment', DATE_SUB(CURRENT_DATE(), INTERVAL 8 DAY), 1, 2),
('expense', 'Electricity & Water Bill', 65.50, 'Utilities', DATE_SUB(CURRENT_DATE(), INTERVAL 5 DAY), 0, NULL),
('income', 'Freelance Web Project', 750.00, 'Freelance', DATE_SUB(CURRENT_DATE(), INTERVAL 3 DAY), 0, NULL),
('expense', 'High-Speed Fiber Internet', 45.00, 'Utilities', DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY), 1, 5),
('expense', 'Dinner with Friends', 62.80, 'Entertainment', DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), 0, NULL);
