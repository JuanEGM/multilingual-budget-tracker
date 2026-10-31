<?php
/**
 * Monthly Fixed Payments & Subscriptions Manager
 * Gestor de Presupuesto Personal y Control de Gastos
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/lang.php';

$pdo = getDB();
$currentYearMonth = date('Y-m');

// -------------------------------------------------------------
// Handle POST Actions (Create, Toggle, Apply, Delete)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 1: Create a new fixed transaction / subscription
    if ($action === 'create') {
        $tipo       = trim($_POST['tipo'] ?? '');
        $concepto   = trim($_POST['concepto'] ?? '');
        $monto      = trim($_POST['monto'] ?? '');
        $categoria  = trim($_POST['categoria'] ?? '');
        $diaPago    = filter_var($_POST['dia_pago'] ?? 1, FILTER_VALIDATE_INT);
        $metodoPago = trim($_POST['metodo_pago'] ?? 'debito');
        $cuentaId   = filter_var($_POST['cuenta_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($cuentaId <= 0) {
            $cuentaId = null;
        }

        $validMethods = ['debito', 'credito', 'transferencia', 'efectivo'];
        if (!in_array($metodoPago, $validMethods, true)) {
            $metodoPago = 'debito';
        }

        $errors = [];
        if (empty($tipo) || empty($concepto) || $monto === '' || empty($categoria) || $diaPago === false) {
            $errors[] = __('val_required_fields');
        }
        if (!in_array($tipo, ['income', 'expense'], true)) {
            $errors[] = __('val_invalid_type');
        }
        if (!is_numeric($monto) || (float) $monto <= 0) {
            $errors[] = __('val_invalid_amount');
        }
        if (mb_strlen($concepto) > 150) {
            $errors[] = __('val_concept_len');
        }
        if (mb_strlen($categoria) > 50) {
            $errors[] = __('val_category_len');
        }
        if ($diaPago < 1 || $diaPago > 31) {
            $errors[] = __('val_invalid_day');
        }

        if (empty($errors)) {
            try {
                $amountFloat = round((float) $monto, 2);

                $stmt = $pdo->prepare("
                    INSERT INTO transacciones_fijas (tipo, concepto, monto, categoria, dia_pago, metodo_pago, cuenta_id, activo) 
                    VALUES (:tipo, :concepto, :monto, :categoria, :dia_pago, :metodo_pago, :cuenta_id, 1)
                ");
                $stmt->execute([
                    ':tipo'        => $tipo,
                    ':concepto'    => $concepto,
                    ':monto'       => $amountFloat,
                    ':categoria'   => $categoria,
                    ':dia_pago'    => $diaPago,
                    ':metodo_pago' => $metodoPago,
                    ':cuenta_id'   => $cuentaId
                ]);

                // Check if user also requested to log it for the current month right now
                if (!empty($_POST['apply_now'])) {
                    $newId = (int) $pdo->lastInsertId();
                    $daysInCurrentMonth = (int) date('t');
                    $execDay = min($diaPago, $daysInCurrentMonth);
                    $execDate = date('Y-m-') . sprintf('%02d', $execDay);

                    $logStmt = $pdo->prepare("
                        INSERT INTO transacciones (tipo, concepto, monto, categoria, fecha, es_fija, fija_id, cuenta_id, metodo_pago) 
                        VALUES (:tipo, :concepto, :monto, :categoria, :fecha, 1, :fija_id, :cuenta_id, :metodo_pago)
                    ");
                    $logStmt->execute([
                        ':tipo'        => $tipo,
                        ':concepto'    => $concepto,
                        ':monto'       => $amountFloat,
                        ':categoria'   => $categoria,
                        ':fecha'       => $execDate,
                        ':fija_id'     => $newId,
                        ':cuenta_id'   => $cuentaId,
                        ':metodo_pago' => $metodoPago
                    ]);

                    $pdo->prepare("UPDATE transacciones_fijas SET ultima_generacion = :fecha WHERE id = :id")
                        ->execute([':fecha' => $execDate, ':id' => $newId]);

                    // Deduct from or add to account balance if linked
                    if ($cuentaId) {
                        if ($tipo === 'expense') {
                            $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id")
                                ->execute([':monto' => $amountFloat, ':id' => $cuentaId]);
                        } else {
                            $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id")
                                ->execute([':monto' => $amountFloat, ':id' => $cuentaId]);
                        }
                    }
                }

                set_flash('success', __('msg_fixed_added'));
            } catch (PDOException $e) {
                error_log("Error creating recurring transaction: " . $e->getMessage());
                set_flash('danger', __('val_db_error'));
            }
        } else {
            set_flash('danger', implode(' ', $errors));
        }

        header("Location: fijas.php");
        exit;
    }

    // Action 2: Toggle Active / Paused state
    if ($action === 'toggle_status') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id && $id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE transacciones_fijas SET activo = IF(activo = 1, 0, 1) WHERE id = :id");
                $stmt->execute([':id' => $id]);
                set_flash('success', __('msg_fixed_status_updated'));
            } catch (PDOException $e) {
                error_log("Error toggling status: " . $e->getMessage());
            }
        }
        header("Location: fijas.php");
        exit;
    }

    // Action 3: Apply single subscription into current month's transactions
    if ($action === 'apply_single') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id && $id > 0) {
            try {
                $fetchStmt = $pdo->prepare("SELECT * FROM transacciones_fijas WHERE id = :id");
                $fetchStmt->execute([':id' => $id]);
                $item = $fetchStmt->fetch();

                if ($item) {
                    $daysInCurrentMonth = (int) date('t');
                    $execDay = min((int) $item['dia_pago'], $daysInCurrentMonth);
                    $execDate = date('Y-m-') . sprintf('%02d', $execDay);
                    $montoFloat = (float) $item['monto'];

                    $insStmt = $pdo->prepare("
                        INSERT INTO transacciones (tipo, concepto, monto, categoria, fecha, es_fija, fija_id, cuenta_id, metodo_pago) 
                        VALUES (:tipo, :concepto, :monto, :categoria, :fecha, 1, :fija_id, :cuenta_id, :metodo_pago)
                    ");
                    $insStmt->execute([
                        ':tipo'        => $item['tipo'],
                        ':concepto'    => $item['concepto'],
                        ':monto'       => $montoFloat,
                        ':categoria'   => $item['categoria'],
                        ':fecha'       => $execDate,
                        ':fija_id'     => $item['id'],
                        ':cuenta_id'   => $item['cuenta_id'] ?? null,
                        ':metodo_pago' => $item['metodo_pago'] ?? 'debito'
                    ]);

                    $pdo->prepare("UPDATE transacciones_fijas SET ultima_generacion = :fecha WHERE id = :id")
                        ->execute([':fecha' => $execDate, ':id' => $item['id']]);

                    // Deduct from account balance if linked
                    if (!empty($item['cuenta_id'])) {
                        if ($item['tipo'] === 'expense') {
                            $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id")
                                ->execute([':monto' => $montoFloat, ':id' => $item['cuenta_id']]);
                        } else {
                            $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id")
                                ->execute([':monto' => $montoFloat, ':id' => $item['cuenta_id']]);
                        }
                    }

                    set_flash('success', __('msg_fixed_applied'));
                }
            } catch (PDOException $e) {
                error_log("Error applying fixed transaction: " . $e->getMessage());
                set_flash('danger', __('val_db_error'));
            }
        }
        header("Location: fijas.php");
        exit;
    }

    // Action 4: Apply all active pending subscriptions for this month
    if ($action === 'apply_all') {
        try {
            $pendingStmt = $pdo->query("
                SELECT * FROM transacciones_fijas 
                WHERE activo = 1 
                  AND (ultima_generacion IS NULL OR DATE_FORMAT(ultima_generacion, '%Y-%m') != DATE_FORMAT(CURRENT_DATE(), '%Y-%m'))
            ");
            $pendingItems = $pendingStmt->fetchAll();

            $daysInCurrentMonth = (int) date('t');
            $appliedCount = 0;

            foreach ($pendingItems as $item) {
                $execDay = min((int) $item['dia_pago'], $daysInCurrentMonth);
                $execDate = date('Y-m-') . sprintf('%02d', $execDay);
                $montoFloat = (float) $item['monto'];

                $insStmt = $pdo->prepare("
                    INSERT INTO transacciones (tipo, concepto, monto, categoria, fecha, es_fija, fija_id, cuenta_id, metodo_pago) 
                    VALUES (:tipo, :concepto, :monto, :categoria, :fecha, 1, :fija_id, :cuenta_id, :metodo_pago)
                ");
                $insStmt->execute([
                    ':tipo'        => $item['tipo'],
                    ':concepto'    => $item['concepto'],
                    ':monto'       => $montoFloat,
                    ':categoria'   => $item['categoria'],
                    ':fecha'       => $execDate,
                    ':fija_id'     => $item['id'],
                    ':cuenta_id'   => $item['cuenta_id'] ?? null,
                    ':metodo_pago' => $item['metodo_pago'] ?? 'debito'
                ]);

                $pdo->prepare("UPDATE transacciones_fijas SET ultima_generacion = :fecha WHERE id = :id")
                    ->execute([':fecha' => $execDate, ':id' => $item['id']]);

                // Update account balance if linked
                if (!empty($item['cuenta_id'])) {
                    if ($item['tipo'] === 'expense') {
                        $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id")
                            ->execute([':monto' => $montoFloat, ':id' => $item['cuenta_id']]);
                    } else {
                        $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id")
                            ->execute([':monto' => $montoFloat, ':id' => $item['cuenta_id']]);
                    }
                }

                $appliedCount++;
            }

            if ($appliedCount > 0) {
                set_flash('success', __('msg_all_fixed_applied'));
            } else {
                set_flash('info', __('badge_applied_this_month'));
            }
        } catch (PDOException $e) {
            error_log("Error applying all fixed items: " . $e->getMessage());
            set_flash('danger', __('val_db_error'));
        }
        header("Location: fijas.php");
        exit;
    }

    // Action 5: Update recurring transaction
    if ($action === 'update') {
        $id         = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $tipo       = trim($_POST['tipo'] ?? '');
        $concepto   = trim($_POST['concepto'] ?? '');
        $monto      = trim($_POST['monto'] ?? '');
        $categoria  = trim($_POST['categoria'] ?? '');
        $diaPago    = filter_var($_POST['dia_pago'] ?? 1, FILTER_VALIDATE_INT);
        $metodoPago = trim($_POST['metodo_pago'] ?? 'debito');
        $cuentaId   = filter_var($_POST['cuenta_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($cuentaId <= 0) {
            $cuentaId = null;
        }

        $validMethods = ['debito', 'credito', 'transferencia', 'efectivo'];
        if (!in_array($metodoPago, $validMethods, true)) {
            $metodoPago = 'debito';
        }

        $errors = [];
        if (!$id || empty($tipo) || empty($concepto) || $monto === '' || empty($categoria) || $diaPago === false) {
            $errors[] = __('val_required_fields');
        }
        if (!in_array($tipo, ['income', 'expense'], true)) {
            $errors[] = __('val_invalid_type');
        }
        if (!is_numeric($monto) || (float) $monto <= 0) {
            $errors[] = __('val_invalid_amount');
        }
        if (mb_strlen($concepto) > 150) {
            $errors[] = __('val_concept_len');
        }
        if (mb_strlen($categoria) > 50) {
            $errors[] = __('val_category_len');
        }
        if ($diaPago < 1 || $diaPago > 31) {
            $errors[] = __('val_invalid_day');
        }

        if (empty($errors)) {
            try {
                $amountFloat = round((float) $monto, 2);
                $stmt = $pdo->prepare("
                    UPDATE transacciones_fijas 
                    SET tipo = :tipo, concepto = :concepto, monto = :monto, categoria = :categoria, 
                        dia_pago = :dia_pago, metodo_pago = :metodo_pago, cuenta_id = :cuenta_id 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':tipo'        => $tipo,
                    ':concepto'    => $concepto,
                    ':monto'       => $amountFloat,
                    ':categoria'   => $categoria,
                    ':dia_pago'    => $diaPago,
                    ':metodo_pago' => $metodoPago,
                    ':cuenta_id'   => $cuentaId,
                    ':id'          => $id
                ]);

                set_flash('success', __('msg_fixed_updated'));
            } catch (PDOException $e) {
                error_log("Error updating recurring transaction: " . $e->getMessage());
                set_flash('danger', __('val_db_error'));
            }
        } else {
            set_flash('danger', implode(' ', $errors));
        }

        header("Location: fijas.php");
        exit;
    }

    // Action 6: Delete recurring transaction
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id && $id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM transacciones_fijas WHERE id = :id");
                $stmt->execute([':id' => $id]);
                set_flash('success', __('msg_fixed_deleted'));
            } catch (PDOException $e) {
                error_log("Error deleting recurring transaction: " . $e->getMessage());
                set_flash('danger', __('msg_error_delete'));
            }
        }
        header("Location: fijas.php");
        exit;
    }
}

// -------------------------------------------------------------
// Calculate Summary Totals for Active Recurring Transactions
// -------------------------------------------------------------
$fixedExpenses = 0.0;
$fixedIncome   = 0.0;

try {
    $sumStmt = $pdo->query("
        SELECT tipo, COALESCE(SUM(monto), 0) AS total 
        FROM transacciones_fijas 
        WHERE activo = 1 
        GROUP BY tipo
    ");
    $sums = $sumStmt->fetchAll();
    foreach ($sums as $row) {
        if ($row['tipo'] === 'expense') {
            $fixedExpenses = (float) $row['total'];
        } elseif ($row['tipo'] === 'income') {
            $fixedIncome = (float) $row['total'];
        }
    }
} catch (PDOException $e) {
    error_log("Error calculating fixed totals: " . $e->getMessage());
}

$projectedFixedBalance = $fixedIncome - $fixedExpenses;

// -------------------------------------------------------------
// Fetch Available Accounts for Linking
// -------------------------------------------------------------
$availableAccounts = [];
try {
    $accListStmt = $pdo->query("SELECT id, nombre, tipo, saldo_actual FROM cuentas ORDER BY nombre ASC");
    $availableAccounts = $accListStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching accounts: " . $e->getMessage());
}

// -------------------------------------------------------------
// Fetch All Recurring Transactions with Account Names
// -------------------------------------------------------------
$recurringItems = [];
$pendingCount = 0;

try {
    $stmt = $pdo->query("
        SELECT f.id, f.tipo, f.concepto, f.monto, f.categoria, f.dia_pago, f.metodo_pago, f.cuenta_id, f.activo, f.ultima_generacion,
               c.nombre AS nombre_cuenta, c.tipo AS tipo_cuenta,
               (f.ultima_generacion IS NOT NULL AND DATE_FORMAT(f.ultima_generacion, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')) AS ya_registrado_este_mes
        FROM transacciones_fijas f
        LEFT JOIN cuentas c ON f.cuenta_id = c.id
        ORDER BY f.dia_pago ASC, f.id ASC
    ");
    $recurringItems = $stmt->fetchAll();

    foreach ($recurringItems as $item) {
        if ((int)$item['activo'] === 1 && empty($item['ya_registrado_este_mes'])) {
            $pendingCount++;
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching recurring items: " . $e->getMessage());
}

// Preset standard categories
$presetCategories = [
    __('cat_entertainment'),
    __('cat_housing'),
    __('cat_utilities'),
    __('cat_salary'),
    __('cat_health'),
    __('cat_food'),
    __('cat_transport'),
    __('cat_education'),
    __('cat_investments'),
    __('cat_freelance'),
    __('cat_other')
];

$pageTitle = __('recurring_title');
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Section -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 text-slate-900"><?= htmlspecialchars(__('recurring_title')) ?></h1>
        <p class="text-secondary mb-0 small"><?= htmlspecialchars(__('recurring_subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="cuentas.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 rounded-pill px-3 py-2 shadow-sm">
            <i class="bi bi-credit-card-2-front"></i>
            <span><?= htmlspecialchars(__('nav_accounts')) ?></span>
        </a>
        <?php if ($pendingCount > 0): ?>
            <form method="POST" action="fijas.php" class="d-inline">
                <input type="hidden" name="action" value="apply_all">
                <button type="submit" class="btn btn-outline-primary d-inline-flex align-items-center gap-2 rounded-pill px-3 py-2 shadow-sm">
                    <i class="bi bi-calendar-check"></i>
                    <span><?= htmlspecialchars(__('btn_apply_all_pending')) ?> (<?= $pendingCount ?>)</span>
                </button>
            </form>
        <?php endif; ?>
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm px-3 py-2 rounded-pill" data-bs-toggle="modal" data-bs-target="#newFixedModal">
            <i class="bi bi-plus-circle-fill"></i>
            <span><?= htmlspecialchars(__('btn_new_fixed')) ?></span>
        </button>
    </div>
</div>

<!-- Summary Cards for Fixed Recurring Items -->
<div class="row g-3 mb-4">
    <!-- Fixed Income Card -->
    <div class="col-12 col-md-4">
        <div class="card card-stat h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold text-secondary small"><?= htmlspecialchars(__('card_fixed_income')) ?></span>
                <span class="icon-bubble icon-bubble-income">
                    <i class="bi bi-arrow-down-left"></i>
                </span>
            </div>
            <div class="h2 fw-bold text-success mb-1">
                <?= htmlspecialchars(format_currency($fixedIncome)) ?>
            </div>
            <div class="small text-muted d-flex align-items-center gap-1">
                <i class="bi bi-calendar-month text-success"></i>
                <span><?= htmlspecialchars(__('type_income')) ?> / <?= current_lang() === 'es' ? 'mes' : 'month' ?></span>
            </div>
        </div>
    </div>

    <!-- Fixed Expenses Card -->
    <div class="col-12 col-md-4">
        <div class="card card-stat h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold text-secondary small"><?= htmlspecialchars(__('card_fixed_expense')) ?></span>
                <span class="icon-bubble icon-bubble-expense">
                    <i class="bi bi-arrow-up-right"></i>
                </span>
            </div>
            <div class="h2 fw-bold text-danger mb-1">
                <?= htmlspecialchars(format_currency($fixedExpenses)) ?>
            </div>
            <div class="small text-muted d-flex align-items-center gap-1">
                <i class="bi bi-calendar-month text-danger"></i>
                <span><?= htmlspecialchars(__('type_expense')) ?> / <?= current_lang() === 'es' ? 'mes' : 'month' ?></span>
            </div>
        </div>
    </div>

    <!-- Projected Fixed Balance Card -->
    <div class="col-12 col-md-4">
        <div class="card card-stat h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold text-secondary small"><?= htmlspecialchars(__('card_projected_fixed')) ?></span>
                <span class="icon-bubble icon-bubble-purple">
                    <i class="bi bi-arrow-repeat"></i>
                </span>
            </div>
            <div class="h2 fw-bold mb-1 <?= ($projectedFixedBalance >= 0) ? 'text-primary' : 'text-danger' ?>">
                <?= htmlspecialchars(format_currency($projectedFixedBalance)) ?>
            </div>
            <div class="small text-muted d-flex align-items-center gap-1">
                <i class="bi bi-calculator"></i>
                <span><?= current_lang() === 'es' ? 'Proyección mensual' : 'Monthly projection' ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Fixed Subscriptions Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
        <div>
            <h5 class="fw-bold mb-0 text-slate-800"><?= htmlspecialchars(__('recurring_title')) ?></h5>
            <small class="text-muted"><?= count($recurringItems) ?> <?= htmlspecialchars(__('nav_recurring')) ?></small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#newFixedModal">
            <i class="bi bi-plus-lg me-1"></i><?= htmlspecialchars(__('btn_new_fixed')) ?>
        </button>
    </div>

    <div class="card-body p-0">
        <?php if (empty($recurringItems)): ?>
            <!-- Empty State -->
            <div class="text-center py-5 px-3">
                <div class="icon-bubble icon-bubble-purple mb-3" style="width: 64px; height: 64px; font-size: 1.8rem;">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars(__('empty_fixed_title')) ?></h5>
                <p class="text-secondary small mb-4 mx-auto" style="max-width: 460px;">
                    <?= htmlspecialchars(__('empty_fixed_desc')) ?>
                </p>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#newFixedModal">
                    <i class="bi bi-plus-lg me-1"></i><?= htmlspecialchars(__('btn_new_fixed')) ?>
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-custom table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 5%;"><?= htmlspecialchars(__('col_id')) ?></th>
                            <th style="width: 23%;"><?= htmlspecialchars(__('col_concept')) ?></th>
                            <th style="width: 14%;"><?= htmlspecialchars(__('col_category')) ?></th>
                            <th style="width: 13%;"><?= htmlspecialchars(__('col_billing_day')) ?></th>
                            <th style="width: 15%;"><?= htmlspecialchars(__('col_payment_method')) ?></th>
                            <th style="width: 8%;"><?= htmlspecialchars(__('col_type')) ?></th>
                            <th class="text-end" style="width: 12%;"><?= htmlspecialchars(__('col_amount')) ?></th>
                            <th class="text-center" style="width: 5%;"><?= htmlspecialchars(__('col_status')) ?></th>
                            <th class="text-center pe-4" style="width: 5%;"><?= htmlspecialchars(__('col_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recurringItems as $item): ?>
                            <tr>
                                <td class="ps-4 text-muted small fw-mono">
                                    #<?= (int) $item['id'] ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-slate-800">
                                        <?= htmlspecialchars($item['concepto']) ?>
                                    </div>
                                    <div class="small mt-1">
                                        <?php if (!empty($item['ya_registrado_este_mes'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars(__('badge_applied_this_month')) ?>
                                            </span>
                                        <?php elseif ((int)$item['activo'] === 1): ?>
                                            <form method="POST" action="fijas.php" class="d-inline">
                                                <input type="hidden" name="action" value="apply_single">
                                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill small" style="font-size: 0.75rem;">
                                                    <i class="bi bi-plus-circle me-1"></i><?= htmlspecialchars(__('btn_apply_to_month')) ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-category">
                                        <i class="bi bi-tag-fill me-1 text-secondary opacity-75"></i><?= htmlspecialchars($item['categoria']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small fw-medium text-dark d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-calendar-event text-secondary"></i>
                                        <?= htmlspecialchars(__('day_of_month_format', [':day' => $item['dia_pago']])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $methodLabel = __('method_debito');
                                        $methodIcon = 'bi-credit-card';
                                        if ($item['metodo_pago'] === 'credito') {
                                            $methodLabel = __('method_credito');
                                            $methodIcon = 'bi-credit-card-2-front';
                                        } elseif ($item['metodo_pago'] === 'transferencia') {
                                            $methodLabel = __('method_transferencia');
                                            $methodIcon = 'bi-bank';
                                        } elseif ($item['metodo_pago'] === 'efectivo') {
                                            $methodLabel = __('method_efectivo');
                                            $methodIcon = 'bi-cash-stack';
                                        }
                                    ?>
                                    <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
                                        <i class="bi <?= $methodIcon ?> text-primary"></i> <?= htmlspecialchars($methodLabel) ?>
                                    </span>
                                    <?php if (!empty($item['nombre_cuenta'])): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-arrow-return-right me-1"></i><?= htmlspecialchars($item['nombre_cuenta']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['tipo'] === 'income'): ?>
                                        <span class="badge-income d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-arrow-down-left"></i>
                                            <?= htmlspecialchars(__('type_income')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-expense d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-arrow-up-right"></i>
                                            <?= htmlspecialchars(__('type_expense')) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php if ($item['tipo'] === 'income'): ?>
                                        <span class="text-success">+<?= htmlspecialchars(format_currency((float) $item['monto'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">-<?= htmlspecialchars(format_currency((float) $item['monto'])) ?></span>
                                    <?php endif; ?>
                                    <div class="text-muted small" style="font-size: 0.7rem; font-weight: normal;">/ <?= current_lang() === 'es' ? 'mes' : 'mo' ?></div>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="fijas.php" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <?php if ((int)$item['activo'] === 1): ?>
                                            <button type="submit" class="btn btn-sm btn-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" title="<?= htmlspecialchars(__('btn_toggle_status')) ?>">
                                                <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars(__('status_active')) ?>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-secondary-subtle text-secondary border rounded-pill px-2 py-0" title="<?= htmlspecialchars(__('btn_toggle_status')) ?>">
                                                <i class="bi bi-pause-circle me-1"></i><?= htmlspecialchars(__('status_paused')) ?>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                                <td class="text-center pe-4 text-nowrap">
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-primary border-0 rounded-circle me-1" 
                                        style="width: 32px; height: 32px;" 
                                        title="<?= htmlspecialchars(__('btn_edit')) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editFixedModal"
                                        data-id="<?= (int) $item['id'] ?>"
                                        data-tipo="<?= htmlspecialchars($item['tipo']) ?>"
                                        data-concepto="<?= htmlspecialchars($item['concepto'], ENT_QUOTES) ?>"
                                        data-monto="<?= htmlspecialchars((string) $item['monto']) ?>"
                                        data-dia="<?= (int) $item['dia_pago'] ?>"
                                        data-metodo="<?= htmlspecialchars($item['metodo_pago'] ?? 'debito') ?>"
                                        data-cuenta="<?= (int) ($item['cuenta_id'] ?? 0) ?>"
                                        data-categoria="<?= htmlspecialchars($item['categoria'], ENT_QUOTES) ?>"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="fijas.php" class="d-inline" onsubmit="return confirm('<?= htmlspecialchars(__('delete_confirm'), ENT_QUOTES) ?>');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle" style="width: 32px; height: 32px;" title="<?= htmlspecialchars(__('btn_delete')) ?>">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: New Fixed Commitment / Subscription -->
<div class="modal fade" id="newFixedModal" tabindex="-1" aria-labelledby="newFixedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-bubble icon-bubble-purple" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-arrow-repeat"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-slate-800" id="newFixedModalLabel"><?= htmlspecialchars(__('form_fixed_title')) ?></h5>
                        <small class="text-secondary"><?= htmlspecialchars(__('form_fixed_subtitle')) ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="fijas.php" novalidate>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <!-- Type (Income / Expense) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_type')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tipo" id="fixed_type_expense" value="expense" checked>
                                <label class="btn btn-outline-danger w-100 py-2 d-flex align-items-center justify-content-center gap-2 rounded-3" for="fixed_type_expense">
                                    <i class="bi bi-arrow-up-right"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars(__('type_expense')) ?></span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tipo" id="fixed_type_income" value="income">
                                <label class="btn btn-outline-success w-100 py-2 d-flex align-items-center justify-content-center gap-2 rounded-3" for="fixed_type_income">
                                    <i class="bi bi-arrow-down-left"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars(__('type_income')) ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Concept -->
                    <div class="mb-3">
                        <label for="fixed_concepto" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_concept')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-card-text"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" id="fixed_concepto" name="concepto" maxlength="150" placeholder="e.g. Netflix, Spotify, Gym, Rent, Salary" required>
                        </div>
                    </div>

                    <!-- Amount and Day of the Month -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="fixed_monto" class="form-label fw-semibold small text-secondary">
                                <?= htmlspecialchars(__('form_field_amount')) ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary border-end-0 fw-bold">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control border-start-0 ps-0" id="fixed_monto" name="monto" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <label for="fixed_dia_pago" class="form-label fw-semibold small text-secondary">
                                <?= htmlspecialchars(__('form_field_day_of_month')) ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary border-end-0">
                                    <i class="bi bi-calendar-day"></i>
                                </span>
                                <input type="number" min="1" max="31" class="form-control border-start-0 ps-0" id="fixed_dia_pago" name="dia_pago" value="1" required>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method (Debit / Credit / Transfer / Cash) -->
                    <div class="mb-3">
                        <label for="fixed_metodo_pago" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_payment_method')) ?> <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="fixed_metodo_pago" name="metodo_pago" required>
                            <option value="debito"><?= htmlspecialchars(__('method_debito')) ?></option>
                            <option value="credito"><?= htmlspecialchars(__('method_credito')) ?></option>
                            <option value="transferencia"><?= htmlspecialchars(__('method_transferencia')) ?></option>
                            <option value="efectivo"><?= htmlspecialchars(__('method_efectivo')) ?></option>
                        </select>
                    </div>

                    <!-- Link to specific Card or Bank Account -->
                    <div class="mb-3">
                        <label for="fixed_cuenta_id" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_link_account')) ?>
                        </label>
                        <select class="form-select" id="fixed_cuenta_id" name="cuenta_id">
                            <option value="0"><?= htmlspecialchars(__('option_no_account')) ?></option>
                            <?php foreach ($availableAccounts as $acc): ?>
                                <option value="<?= (int) $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['nombre']) ?> (<?= htmlspecialchars(format_currency((float) $acc['saldo_actual'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">
                            <?= current_lang() === 'es' ? 'Permite vincular este gasto a una tarjeta o cuenta bancaria para calcular su saldo restante proyectado.' : 'Links this recurring expense to a card to calculate its remaining balance.' ?>
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="mb-3">
                        <label for="fixed_categoria" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_category')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-tag"></i>
                            </span>
                            <input class="form-control border-start-0 ps-0" list="fixedCategoriesList" id="fixed_categoria" name="categoria" maxlength="50" placeholder="<?= htmlspecialchars(__('form_select_category')) ?>" required>
                            <datalist id="fixedCategoriesList">
                                <?php foreach ($presetCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <!-- Apply to current month checkbox -->
                    <div class="form-check form-switch mt-3 p-3 bg-light rounded-3 border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="apply_now" name="apply_now" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="apply_now">
                            <?= htmlspecialchars(__('btn_apply_to_month')) ?> (<?= date('F Y') ?>)
                        </label>
                        <div class="text-muted small ms-4">
                            <?= current_lang() === 'es' ? 'Crea también el registro en el libro diario para este mes actual y descuenta del saldo de la tarjeta si está vinculada.' : 'Also log an entry in your transactions ledger for the current month and update card balance.' ?>
                        </div>
                    </div>

                </div>

                <div class="modal-footer border-top py-2 px-4">
                    <button type="button" class="btn btn-sm btn-light px-3 rounded-pill text-secondary" data-bs-dismiss="modal">
                        <?= htmlspecialchars(__('btn_cancel')) ?>
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary px-4 rounded-pill shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i>
                        <?= htmlspecialchars(__('btn_save')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<!-- Modal: Edit Fixed Commitment / Subscription -->
<div class="modal fade" id="editFixedModal" tabindex="-1" aria-labelledby="editFixedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-bubble icon-bubble-purple" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-pencil-square"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-slate-800" id="editFixedModalLabel"><?= htmlspecialchars(__('form_edit_fixed_title')) ?></h5>
                        <small class="text-secondary"><?= htmlspecialchars(__('form_edit_fixed_subtitle')) ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="fijas.php" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_fixed_id" value="">

                <div class="modal-body p-4">
                    <!-- Type (Income / Expense) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_type')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tipo" id="edit_fixed_type_expense" value="expense">
                                <label class="btn btn-outline-danger w-100 py-2 d-flex align-items-center justify-content-center gap-2 rounded-3" for="edit_fixed_type_expense">
                                    <i class="bi bi-arrow-up-right"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars(__('type_expense')) ?></span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tipo" id="edit_fixed_type_income" value="income">
                                <label class="btn btn-outline-success w-100 py-2 d-flex align-items-center justify-content-center gap-2 rounded-3" for="edit_fixed_type_income">
                                    <i class="bi bi-arrow-down-left"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars(__('type_income')) ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Concept -->
                    <div class="mb-3">
                        <label for="edit_fixed_concepto" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_concept')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-card-text"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" id="edit_fixed_concepto" name="concepto" maxlength="150" required>
                        </div>
                    </div>

                    <!-- Amount and Day of the Month -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="edit_fixed_monto" class="form-label fw-semibold small text-secondary">
                                <?= htmlspecialchars(__('form_field_amount')) ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary border-end-0 fw-bold">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control border-start-0 ps-0" id="edit_fixed_monto" name="monto" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <label for="edit_fixed_dia_pago" class="form-label fw-semibold small text-secondary">
                                <?= htmlspecialchars(__('form_field_day_of_month')) ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary border-end-0">
                                    <i class="bi bi-calendar-day"></i>
                                </span>
                                <input type="number" min="1" max="31" class="form-control border-start-0 ps-0" id="edit_fixed_dia_pago" name="dia_pago" required>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="mb-3">
                        <label for="edit_fixed_metodo_pago" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_payment_method')) ?> <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="edit_fixed_metodo_pago" name="metodo_pago" required>
                            <option value="debito"><?= htmlspecialchars(__('method_debito')) ?></option>
                            <option value="credito"><?= htmlspecialchars(__('method_credito')) ?></option>
                            <option value="transferencia"><?= htmlspecialchars(__('method_transferencia')) ?></option>
                            <option value="efectivo"><?= htmlspecialchars(__('method_efectivo')) ?></option>
                        </select>
                    </div>

                    <!-- Link to specific Card or Bank Account -->
                    <div class="mb-3">
                        <label for="edit_fixed_cuenta_id" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_link_account')) ?>
                        </label>
                        <select class="form-select" id="edit_fixed_cuenta_id" name="cuenta_id">
                            <option value="0"><?= htmlspecialchars(__('option_no_account')) ?></option>
                            <?php foreach ($availableAccounts as $acc): ?>
                                <option value="<?= (int) $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['nombre']) ?> (<?= htmlspecialchars(format_currency((float) $acc['saldo_actual'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Category -->
                    <div class="mb-3">
                        <label for="edit_fixed_categoria" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_category')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-tag"></i>
                            </span>
                            <input class="form-control border-start-0 ps-0" list="fixedCategoriesList" id="edit_fixed_categoria" name="categoria" maxlength="50" required>
                        </div>
                    </div>

                </div>

                <div class="modal-footer border-top py-2 px-4">
                    <button type="button" class="btn btn-sm btn-light px-3 rounded-pill text-secondary" data-bs-dismiss="modal">
                        <?= htmlspecialchars(__('btn_cancel')) ?>
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary px-4 rounded-pill shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i>
                        <?= htmlspecialchars(__('btn_save')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Wire up Edit Fixed Transaction Modal with clicked row data
    const editFixedModal = document.getElementById('editFixedModal');
    if (editFixedModal) {
        editFixedModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const tipo = button.getAttribute('data-tipo');
            const concepto = button.getAttribute('data-concepto');
            const monto = button.getAttribute('data-monto');
            const dia = button.getAttribute('data-dia');
            const metodo = button.getAttribute('data-metodo');
            const cuenta = button.getAttribute('data-cuenta');
            const categoria = button.getAttribute('data-categoria');

            editFixedModal.querySelector('#edit_fixed_id').value = id;
            editFixedModal.querySelector('#edit_fixed_concepto').value = concepto;
            editFixedModal.querySelector('#edit_fixed_monto').value = parseFloat(monto).toFixed(2);
            editFixedModal.querySelector('#edit_fixed_dia_pago').value = dia;
            editFixedModal.querySelector('#edit_fixed_categoria').value = categoria;
            editFixedModal.querySelector('#edit_fixed_metodo_pago').value = metodo;
            editFixedModal.querySelector('#edit_fixed_cuenta_id').value = cuenta;

            if (tipo === 'income') {
                editFixedModal.querySelector('#edit_fixed_type_income').checked = true;
            } else {
                editFixedModal.querySelector('#edit_fixed_type_expense').checked = true;
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
