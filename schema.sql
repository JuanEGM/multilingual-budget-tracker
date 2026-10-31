-- ========================================================
-- Gestor de Presupuesto Personal y Control de Gastos
-- Database Schema (Clean - No Initial Sample Data)
-- Compatible with MySQL 5.7+, MySQL 8.x and MariaDB 10.3+
-- ========================================================

-- Create database if it does not exist
CREATE DATABASE IF NOT EXISTS `gestor_financiero` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `gestor_financiero`;

-- --------------------------------------------------------
-- Table structure for `transacciones` (Libro diario)
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
-- Table structure for `transacciones_fijas` (Pagos fijos y suscripciones)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transacciones_fijas`;

CREATE TABLE `transacciones_fijas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo` ENUM('income', 'expense') NOT NULL COMMENT 'Transaction type: income or expense',
    `concepto` VARCHAR(150) NOT NULL COMMENT 'Subscription / Fixed payment name',
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
