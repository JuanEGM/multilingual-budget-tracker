-- ========================================================
-- Gestor de Presupuesto Personal y Control de Gastos
-- Database Schema - Phase 1
-- Compatible with MySQL 5.7+, MySQL 8.x and MariaDB 10.3+
-- ========================================================

-- Create database if it does not exist
CREATE DATABASE IF NOT EXISTS `gestor_financiero` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `gestor_financiero`;

-- --------------------------------------------------------
-- Table structure for `transacciones`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transacciones`;

CREATE TABLE `transacciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo` ENUM('income', 'expense') NOT NULL COMMENT 'Transaction type: income or expense',
    `concepto` VARCHAR(150) NOT NULL COMMENT 'Short description of the transaction',
    `monto` DECIMAL(10, 2) NOT NULL COMMENT 'Transaction amount with 2 decimal places',
    `categoria` VARCHAR(50) NOT NULL COMMENT 'Classification category',
    `fecha` DATE NOT NULL COMMENT 'Date when the transaction occurred',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Audit timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Indexes for performance optimization
-- --------------------------------------------------------
CREATE INDEX `idx_transacciones_fecha` ON `transacciones` (`fecha`);
CREATE INDEX `idx_transacciones_tipo` ON `transacciones` (`tipo`);

-- --------------------------------------------------------
-- Initial Sample Data for Quick Testing
-- --------------------------------------------------------
INSERT INTO `transacciones` (`tipo`, `concepto`, `monto`, `categoria`, `fecha`) VALUES
('income', 'Monthly Salary', 3500.00, 'Salary', DATE_SUB(CURRENT_DATE(), INTERVAL 10 DAY)),
('expense', 'Supermarket Groceries', 154.20, 'Food', DATE_SUB(CURRENT_DATE(), INTERVAL 8 DAY)),
('expense', 'Electricity & Water Bill', 65.50, 'Utilities', DATE_SUB(CURRENT_DATE(), INTERVAL 5 DAY)),
('income', 'Freelance Web Project', 750.00, 'Freelance', DATE_SUB(CURRENT_DATE(), INTERVAL 3 DAY)),
('expense', 'Internet & Mobile Service', 45.00, 'Utilities', DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY)),
('expense', 'Dinner with Friends', 62.80, 'Entertainment', DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY));
