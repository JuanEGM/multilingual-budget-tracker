<?php
/**
 * Delete Transaction Controller
 * Gestor de Presupuesto Personal y Control de Gastos - Phase 2
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/lang.php';

// Accept ID via POST (preferred) or GET (fallback)
$id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
} elseif (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
}

if ($id === false || $id <= 0) {
    set_flash('danger', __('msg_error_delete'));
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
    exit;
}

try {
    $pdo = getDB();

    // 1. Fetch transaction details to reverse account balance if linked
    $fetchStmt = $pdo->prepare("SELECT id, tipo, monto, cuenta_id, cuenta_destino_id FROM transacciones WHERE id = :id");
    $fetchStmt->execute([':id' => $id]);
    $tx = $fetchStmt->fetch();

    if ($tx) {
        $montoFloat = (float) $tx['monto'];
        $cuentaId = !empty($tx['cuenta_id']) ? (int) $tx['cuenta_id'] : null;
        $cuentaDestinoId = !empty($tx['cuenta_destino_id']) ? (int) $tx['cuenta_destino_id'] : null;

        $pdo->beginTransaction();

        // If transfer, reverse both origin and destination accounts
        if ($tx['tipo'] === 'transfer') {
            if ($cuentaId) {
                // Return funds back to origin account
                $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id")
                    ->execute([':monto' => $montoFloat, ':id' => $cuentaId]);
            }
            if ($cuentaDestinoId) {
                // Deduct transferred funds from destination account
                $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id")
                    ->execute([':monto' => $montoFloat, ':id' => $cuentaDestinoId]);
            }
        } elseif ($cuentaId) {
            // For regular income/expense, reverse financial impact
            if ($tx['tipo'] === 'expense') {
                // Re-add money back to account
                $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id")
                    ->execute([':monto' => $montoFloat, ':id' => $cuentaId]);
            } elseif ($tx['tipo'] === 'income') {
                // Deduct money from account
                $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id")
                    ->execute([':monto' => $montoFloat, ':id' => $cuentaId]);
            }
        }

        // 2. Delete transaction
        $delStmt = $pdo->prepare("DELETE FROM transacciones WHERE id = :id");
        $delStmt->execute([':id' => $id]);

        $pdo->commit();

        set_flash('success', __('msg_success_delete'));
    } else {
        set_flash('warning', __('msg_error_not_found'));
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database Delete Error: " . $e->getMessage());
    set_flash('danger', __('msg_error_delete'));
}

// Redirect back to referring page or dashboard
$referer = $_SERVER['HTTP_REFERER'] ?? '../index.php';
header("Location: " . $referer);
exit;
