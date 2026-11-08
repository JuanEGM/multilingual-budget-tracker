<?php
/**
 * Auto-Migration Utility
 * Ensures tables and columns for Accounts, Transfers, Debit/Credit Cards
 * and Biweekly/Annual/Monthly Subscriptions exist dynamically.
 */

declare(strict_types=1);

function runAutoMigrations(PDO $pdo): void
{
    static $migrated = false;
    if ($migrated) {
        return;
    }

    try {
        // 1. Table: cuentas (Bank Accounts, Debit and Credit Cards, Cash Wallets)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `cuentas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nombre` VARCHAR(100) NOT NULL COMMENT 'Name of account or card',
                `tipo` ENUM('tarjeta_debito', 'tarjeta_credito', 'cuenta_bancaria', 'efectivo') NOT NULL DEFAULT 'cuenta_bancaria',
                `saldo_actual` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Current balance or card balance',
                `color` VARCHAR(20) NOT NULL DEFAULT '#2563eb',
                `icono` VARCHAR(50) NOT NULL DEFAULT 'bi-credit-card',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Check and add columns to `transacciones_fijas`
        $colsStmt = $pdo->query("SHOW COLUMNS FROM `transacciones_fijas`");
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('metodo_pago', $cols, true)) {
            $pdo->exec("ALTER TABLE `transacciones_fijas` ADD COLUMN `metodo_pago` ENUM('debito', 'credito', 'transferencia', 'efectivo') NOT NULL DEFAULT 'debito' AFTER `dia_pago`");
        }
        if (!in_array('cuenta_id', $cols, true)) {
            $pdo->exec("ALTER TABLE `transacciones_fijas` ADD COLUMN `cuenta_id` INT NULL AFTER `metodo_pago`");
        }
        if (!in_array('frecuencia', $cols, true)) {
            $pdo->exec("ALTER TABLE `transacciones_fijas` ADD COLUMN `frecuencia` ENUM('mensual', 'quincenal', 'anual') NOT NULL DEFAULT 'mensual' AFTER `categoria`");
        }
        if (!in_array('dia_pago_2', $cols, true)) {
            $pdo->exec("ALTER TABLE `transacciones_fijas` ADD COLUMN `dia_pago_2` TINYINT NULL DEFAULT NULL AFTER `dia_pago`");
        }
        if (!in_array('mes_pago', $cols, true)) {
            $pdo->exec("ALTER TABLE `transacciones_fijas` ADD COLUMN `mes_pago` TINYINT NULL DEFAULT 1 AFTER `dia_pago_2`");
        }

        // 3. Check and add columns to `transacciones`
        $txColsStmt = $pdo->query("SHOW COLUMNS FROM `transacciones`");
        $txCols = $txColsStmt->fetchAll(PDO::FETCH_COLUMN);

        // Modify `tipo` to include 'transfer' if not already present
        $pdo->exec("ALTER TABLE `transacciones` MODIFY COLUMN `tipo` ENUM('income', 'expense', 'transfer') NOT NULL");

        if (!in_array('cuenta_id', $txCols, true)) {
            $pdo->exec("ALTER TABLE `transacciones` ADD COLUMN `cuenta_id` INT NULL AFTER `fija_id`");
        }
        if (!in_array('cuenta_destino_id', $txCols, true)) {
            $pdo->exec("ALTER TABLE `transacciones` ADD COLUMN `cuenta_destino_id` INT NULL AFTER `cuenta_id`");
        }
        if (!in_array('metodo_pago', $txCols, true)) {
            $pdo->exec("ALTER TABLE `transacciones` ADD COLUMN `metodo_pago` VARCHAR(50) NULL AFTER `cuenta_destino_id`");
        }

        $migrated = true;
    } catch (PDOException $e) {
        error_log("Migration error: " . $e->getMessage());
    }
}
