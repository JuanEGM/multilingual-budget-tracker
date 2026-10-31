<?php
/**
 * Delete Transaction Controller
 * Gestor de Presupuesto Personal y Control de Gastos - Phase 1
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/lang.php';

// Accept ID via POST (preferred) or GET (fallback)
$id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
} elseif (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
}

if ($id === false || $id <= 0) {
    set_flash('danger', __('msg_error_delete'));
    header("Location: index.php");
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM transacciones WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        set_flash('success', __('msg_success_delete'));
    } else {
        set_flash('warning', __('msg_error_not_found'));
    }
} catch (PDOException $e) {
    error_log("Database Delete Error: " . $e->getMessage());
    set_flash('danger', __('msg_error_delete'));
}

// Redirect back to dashboard
header("Location: index.php");
exit;
