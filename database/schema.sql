-- ========================================================
-- Gestor de Presupuesto Personal y Control de Gastos
-- Database Schema - Phase 2 with Inter-Account Transfers & Subscriptions
-- Compatible with MySQL 5.7+, MySQL 8.x and MariaDB 10.3+
-- ========================================================

-- Create database if it does not exist
CREATE DATABASE IF NOT EXISTS `gestor_financiero` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `gestor_financiero`;

-- --------------------------------------------------------
-- Table structure for `cuentas` (Cuentas bancarias y tarjetas)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transacciones`;
DROP TABLE IF EXISTS `transacciones_fijas`;
DROP TABLE IF EXISTS `cuentas`;

CREATE TABLE `cuentas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Name of account or card (e.g. Visa Debito, Mastercard Credito, Banco)',
    `tipo` ENUM('tarjeta_debito', 'tarjeta_credito', 'cuenta_bancaria', 'efectivo') NOT NULL DEFAULT 'cuenta_bancaria',
    `saldo_actual` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Current available balance or card balance',
    `color` VARCHAR(20) NOT NULL DEFAULT '#2563eb' COMMENT 'Color accent for card UI',
    `icono` VARCHAR(50) NOT NULL DEFAULT 'bi-credit-card' COMMENT 'Icon identifier',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Audit timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `transacciones_fijas` (Pagos fijos y suscripciones)
-- --------------------------------------------------------
CREATE TABLE `transacciones_fijas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo` ENUM('income', 'expense') NOT NULL COMMENT 'Transaction type: income or expense',
    `concepto` VARCHAR(150) NOT NULL COMMENT 'Subscription / Fixed payment name',
    `monto` DECIMAL(10, 2) NOT NULL COMMENT 'Recurring amount (per cycle)',
    `categoria` VARCHAR(50) NOT NULL COMMENT 'Classification category',
    `frecuencia` ENUM('mensual', 'quincenal', 'anual') NOT NULL DEFAULT 'mensual' COMMENT 'Recurrence frequency',
    `dia_pago` TINYINT NOT NULL DEFAULT 1 COMMENT 'Scheduled primary day (1-31)',
    `dia_pago_2` TINYINT NULL DEFAULT NULL COMMENT 'Second day for biweekly cycles (e.g. 15 or 30)',
    `mes_pago` TINYINT NULL DEFAULT 1 COMMENT 'Month of execution for annual cycles (1-12)',
    `metodo_pago` ENUM('debito', 'credito', 'transferencia', 'efectivo') NOT NULL DEFAULT 'debito' COMMENT 'Payment method',
    `cuenta_id` INT NULL COMMENT 'Associated bank account or card',
    `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Paused',
    `ultima_generacion` DATE NULL COMMENT 'Last date when applied to transacciones table',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Audit timestamp',
    CONSTRAINT `fk_fijas_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `transacciones` (Libro diario de transacciones)
-- --------------------------------------------------------
CREATE TABLE `transacciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo` ENUM('income', 'expense', 'transfer') NOT NULL COMMENT 'Transaction type: income, expense or internal transfer',
    `concepto` VARCHAR(150) NOT NULL COMMENT 'Short description of the transaction',
    `monto` DECIMAL(10, 2) NOT NULL COMMENT 'Transaction amount with 2 decimal places',
    `categoria` VARCHAR(50) NOT NULL COMMENT 'Classification category',
    `fecha` DATE NOT NULL COMMENT 'Date when the transaction occurred',
    `es_fija` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if generated from a recurring subscription or payment',
    `fija_id` INT NULL COMMENT 'Reference to transacciones_fijas if applicable',
    `cuenta_id` INT NULL COMMENT 'Origin card/account (or main account for income/expense)',
    `cuenta_destino_id` INT NULL COMMENT 'Destination card/account for internal transfers',
    `metodo_pago` VARCHAR(50) NULL COMMENT 'Payment method',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Audit timestamp',
    CONSTRAINT `fk_transacciones_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_transacciones_destino` FOREIGN KEY (`cuenta_destino_id`) REFERENCES `cuentas` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_transacciones_fija` FOREIGN KEY (`fija_id`) REFERENCES `transacciones_fijas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Indexes for performance optimization
-- --------------------------------------------------------
CREATE INDEX `idx_transacciones_fecha` ON `transacciones` (`fecha`);
CREATE INDEX `idx_transacciones_tipo` ON `transacciones` (`tipo`);
CREATE INDEX `idx_transacciones_cuenta` ON `transacciones` (`cuenta_id`);
CREATE INDEX `idx_transacciones_destino` ON `transacciones` (`cuenta_destino_id`);
CREATE INDEX `idx_transacciones_fijas_activo` ON `transacciones_fijas` (`activo`);
CREATE INDEX `idx_transacciones_fijas_dia` ON `transacciones_fijas` (`dia_pago`);
CREATE INDEX `idx_transacciones_fijas_frecuencia` ON `transacciones_fijas` (`frecuencia`);
CREATE INDEX `idx_transacciones_fijas_cuenta` ON `transacciones_fijas` (`cuenta_id`);
