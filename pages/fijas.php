<?php
/**
 * Fixed Payments & Subscriptions Manager (Monthly, Biweekly, Annual)
 * Gestor de Presupuesto Personal y Control de Gastos
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/lang.php';

$pdo = getDB();
$currentYearMonth = date('Y-m');

// -------------------------------------------------------------
// Helper: Map month number (1-12) to localized name
// -------------------------------------------------------------
function getMonthName(int $m): string
{
    $key = "month_{$m}";
    return __($key);
}

// -------------------------------------------------------------
// Handle POST Actions (Create, Toggle, Apply, Delete, Update)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 1: Create a new fixed transaction / subscription
    if ($action === 'create') {
        $tipo       = trim($_POST['tipo'] ?? '');
        $concepto   = trim($_POST['concepto'] ?? '');
        $monto      = trim($_POST['monto'] ?? '');
        $categoria  = trim($_POST['categoria'] ?? '');
        $frecuencia = trim($_POST['frecuencia'] ?? 'mensual');
        $diaPago    = filter_var($_POST['dia_pago'] ?? 1, FILTER_VALIDATE_INT);
        $diaPago2   = filter_var($_POST['dia_pago_2'] ?? 0, FILTER_VALIDATE_INT);
        $mesPago    = filter_var($_POST['mes_pago'] ?? 1, FILTER_VALIDATE_INT);
        $metodoPago = trim($_POST['metodo_pago'] ?? 'debito');
        $cuentaId   = filter_var($_POST['cuenta_id'] ?? 0, FILTER_VALIDATE_INT);

        if ($cuentaId <= 0) {
            $cuentaId = null;
        }

        $validFrequencies = ['mensual', 'quincenal', 'anual'];
        if (!in_array($frecuencia, $validFrequencies, true)) {
            $frecuencia = 'mensual';
        }

        if ($frecuencia === 'quincenal') {
            if ($diaPago2 === false || $diaPago2 < 1 || $diaPago2 > 31) {
                // Sane default: 15 days after primary day
                $diaPago2 = ($diaPago <= 15) ? min(31, $diaPago + 15) : max(1, $diaPago - 15);
            }
        } else {
            $diaPago2 = null;
        }

        if ($frecuencia !== 'anual' || $mesPago < 1 || $mesPago > 12) {
            $mesPago = 1;
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
                    INSERT INTO transacciones_fijas (tipo, concepto, monto, categoria, frecuencia, dia_pago, dia_pago_2, mes_pago, metodo_pago, cuenta_id, activo) 
                    VALUES (:tipo, :concepto, :monto, :categoria, :frecuencia, :dia_pago, :dia_pago_2, :mes_pago, :metodo_pago, :cuenta_id, 1)
                ");
                $stmt->execute([
                    ':tipo'        => $tipo,
                    ':concepto'    => $concepto,
                    ':monto'       => $amountFloat,
                    ':categoria'   => $categoria,
                    ':frecuencia'  => $frecuencia,
                    ':dia_pago'    => $diaPago,
                    ':dia_pago_2'  => $diaPago2,
                    ':mes_pago'    => $mesPago,
                    ':metodo_pago' => $metodoPago,
                    ':cuenta_id'   => $cuentaId
                ]);

                // Check if user also requested to log it for the current cycle right now
                if (!empty($_POST['apply_now'])) {
                    $newId = (int) $pdo->lastInsertId();
                    $execDate = date('Y-m-d');

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
                    $execDate = date('Y-m-d');
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

    // Action 4: Apply all active pending subscriptions for this cycle
    if ($action === 'apply_all') {
        try {
            $pendingStmt = $pdo->query("
                SELECT * FROM transacciones_fijas 
                WHERE activo = 1 
                  AND (
                      (frecuencia = 'mensual' AND (ultima_generacion IS NULL OR DATE_FORMAT(ultima_generacion, '%Y-%m') != DATE_FORMAT(CURRENT_DATE(), '%Y-%m')))
                      OR (frecuencia = 'anual' AND (ultima_generacion IS NULL OR DATE_FORMAT(ultima_generacion, '%Y') != DATE_FORMAT(CURRENT_DATE(), '%Y')))
                      OR (frecuencia = 'quincenal' AND (ultima_generacion IS NULL OR DATEDIFF(CURRENT_DATE(), ultima_generacion) >= 14))
                  )
            ");
            $pendingItems = $pendingStmt->fetchAll();

            $appliedCount = 0;
            $execDate = date('Y-m-d');

            foreach ($pendingItems as $item) {
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
        $frecuencia = trim($_POST['frecuencia'] ?? 'mensual');
        $diaPago    = filter_var($_POST['dia_pago'] ?? 1, FILTER_VALIDATE_INT);
        $diaPago2   = filter_var($_POST['dia_pago_2'] ?? 0, FILTER_VALIDATE_INT);
        $mesPago    = filter_var($_POST['mes_pago'] ?? 1, FILTER_VALIDATE_INT);
        $metodoPago = trim($_POST['metodo_pago'] ?? 'debito');
        $cuentaId   = filter_var($_POST['cuenta_id'] ?? 0, FILTER_VALIDATE_INT);

        if ($cuentaId <= 0) {
            $cuentaId = null;
        }

        $validFrequencies = ['mensual', 'quincenal', 'anual'];
        if (!in_array($frecuencia, $validFrequencies, true)) {
            $frecuencia = 'mensual';
        }

        if ($frecuencia === 'quincenal') {
            if ($diaPago2 === false || $diaPago2 < 1 || $diaPago2 > 31) {
                $diaPago2 = ($diaPago <= 15) ? min(31, $diaPago + 15) : max(1, $diaPago - 15);
            }
        } else {
            $diaPago2 = null;
        }

        if ($frecuencia !== 'anual' || $mesPago < 1 || $mesPago > 12) {
            $mesPago = 1;
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
                        frecuencia = :frecuencia, dia_pago = :dia_pago, dia_pago_2 = :dia_pago_2, mes_pago = :mes_pago, 
                        metodo_pago = :metodo_pago, cuenta_id = :cuenta_id 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':tipo'        => $tipo,
                    ':concepto'    => $concepto,
                    ':monto'       => $amountFloat,
                    ':categoria'   => $categoria,
                    ':frecuencia'  => $frecuencia,
                    ':dia_pago'    => $diaPago,
                    ':dia_pago_2'  => $diaPago2,
                    ':mes_pago'    => $mesPago,
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
// Calculate Summary Totals for Active Recurring Transactions (Monthly Equivalent)
// -------------------------------------------------------------
$fixedExpenses = 0.0;
$fixedIncome   = 0.0;

try {
    $sumStmt = $pdo->query("
        SELECT tipo, frecuencia, monto 
        FROM transacciones_fijas 
        WHERE activo = 1
    ");
    $activeItems = $sumStmt->fetchAll();

    foreach ($activeItems as $row) {
        $freq = $row['frecuencia'] ?? 'mensual';
        $monto = (float) $row['monto'];

        if ($freq === 'quincenal') {
            $monthlyEquiv = $monto * 2;
        } elseif ($freq === 'anual') {
            $monthlyEquiv = $monto / 12;
        } else {
            $monthlyEquiv = $monto;
        }

        if ($row['tipo'] === 'expense') {
            $fixedExpenses += $monthlyEquiv;
        } elseif ($row['tipo'] === 'income') {
            $fixedIncome += $monthlyEquiv;
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
        SELECT f.id, f.tipo, f.concepto, f.monto, f.categoria, f.frecuencia, f.dia_pago, f.dia_pago_2, f.mes_pago, f.metodo_pago, f.cuenta_id, f.activo, f.ultima_generacion,
               c.nombre AS nombre_cuenta, c.tipo AS tipo_cuenta
        FROM transacciones_fijas f
        LEFT JOIN cuentas c ON f.cuenta_id = c.id
        ORDER BY f.frecuencia DESC, f.dia_pago ASC, f.id ASC
    ");
    $rawItems = $stmt->fetchAll();

    $currentYm = date('Y-m');
    $currentYear = date('Y');

    foreach ($rawItems as $item) {
        $freq = $item['frecuencia'] ?? 'mensual';
        $isApplied = false;

        if (!empty($item['ultima_generacion'])) {
            $genYm = date('Y-m', strtotime($item['ultima_generacion']));
            $genYear = date('Y', strtotime($item['ultima_generacion']));

            if ($freq === 'anual') {
                $isApplied = ($genYear === $currentYear);
            } elseif ($freq === 'quincenal') {
                // If generated within last 14 days
                $diffDays = (int) (abs(time() - strtotime($item['ultima_generacion'])) / 86400);
                $isApplied = ($diffDays < 14);
            } else {
                $isApplied = ($genYm === $currentYm);
            }
        }

        $item['ya_registrado_este_ciclo'] = $isApplied;
        if ((int)$item['activo'] === 1 && !$isApplied) {
            $pendingCount++;
        }
        $recurringItems[] = $item;
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
        <a href="<?= htmlspecialchars(app_url('pages/cuentas.php')) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 rounded-pill px-3 py-2 shadow-sm">
            <i class="bi bi-credit-card-2-front"></i>
            <span><?= htmlspecialchars(__('nav_accounts')) ?></span>
        </a>
        <?php if ($pendingCount > 0): ?>
            <form method="POST" action="<?= htmlspecialchars(app_url('pages/fijas.php')) ?>" class="d-inline">
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

<!-- Summary Cards for Fixed Recurring Items (Monthly Equivalent Projection) -->
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
                <span><?= htmlspecialchars(__('type_income')) ?> / <?= htmlspecialchars(__('monthly_equivalent')) ?></span>
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
                <span><?= htmlspecialchars(__('type_expense')) ?> / <?= htmlspecialchars(__('monthly_equivalent')) ?></span>
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
                <span><?= htmlspecialchars(__('card_net_balance')) ?> / <?= htmlspecialchars(__('monthly_equivalent')) ?></span>
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
                <table class="table table-sm table-custom table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 5%;"><?= htmlspecialchars(__('col_id')) ?></th>
                            <th style="width: 22%;"><?= htmlspecialchars(__('col_concept')) ?></th>
                            <th style="width: 12%;"><?= htmlspecialchars(__('col_category')) ?></th>
                            <th style="width: 10%;"><?= htmlspecialchars(__('col_frequency')) ?></th>
                            <th style="width: 16%;"><?= htmlspecialchars(__('col_billing_day')) ?></th>
                            <th style="width: 14%;"><?= htmlspecialchars(__('col_payment_method')) ?></th>
                            <th style="width: 7%;"><?= htmlspecialchars(__('col_type')) ?></th>
                            <th class="text-end" style="width: 11%;"><?= htmlspecialchars(__('col_amount')) ?></th>
                            <th class="text-center" style="width: 6%;"><?= htmlspecialchars(__('col_status')) ?></th>
                            <th class="text-center pe-4" style="width: 7%;"><?= htmlspecialchars(__('col_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recurringItems as $item): ?>
                            <?php
                                $freq = $item['frecuencia'] ?? 'mensual';
                                $freqBadgeClass = 'bg-primary-subtle text-primary';
                                $freqLabel = __('freq_mensual');
                                if ($freq === 'quincenal') {
                                    $freqBadgeClass = 'bg-warning-subtle text-warning-emphasis';
                                    $freqLabel = __('freq_quincenal');
                                } elseif ($freq === 'anual') {
                                    $freqBadgeClass = 'bg-purple-subtle text-purple';
                                    $freqLabel = __('freq_anual');
                                }

                                // Monthly equivalent calculation
                                $montoItem = (float) $item['monto'];
                                if ($freq === 'quincenal') {
                                    $equivMensual = $montoItem * 2;
                                } elseif ($freq === 'anual') {
                                    $equivMensual = $montoItem / 12;
                                } else {
                                    $equivMensual = $montoItem;
                                }
                            ?>
                            <tr>
                                <td class="ps-4 text-muted small fw-mono">
                                    #<?= (int) $item['id'] ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-slate-800">
                                        <?= htmlspecialchars($item['concepto']) ?>
                                    </div>
                                    <div class="small mt-1">
                                        <?php if (!empty($item['ya_registrado_este_ciclo'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars(__('badge_applied_this_month')) ?>
                                            </span>
                                        <?php elseif ((int)$item['activo'] === 1): ?>
                                            <form method="POST" action="<?= htmlspecialchars(app_url('pages/fijas.php')) ?>" class="d-inline">
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
                                    <span class="badge <?= $freqBadgeClass ?> border-0">
                                        <?= htmlspecialchars($freqLabel) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small fw-medium text-dark d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-calendar-event text-secondary"></i>
                                        <?php if ($freq === 'quincenal'): ?>
                                            <?= htmlspecialchars(__('day_biweekly_format', [
                                                ':d1' => $item['dia_pago'],
                                                ':d2' => $item['dia_pago_2'] ?? 15
                                            ])) ?>
                                        <?php elseif ($freq === 'anual'): ?>
                                            <?= htmlspecialchars(__('day_annual_format', [
                                                ':day'   => $item['dia_pago'],
                                                ':month' => getMonthName((int)($item['mes_pago'] ?? 1))
                                            ])) ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars(__('day_of_month_format', [':day' => $item['dia_pago']])) ?>
                                        <?php endif; ?>
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
                                        <span class="text-success">+<?= htmlspecialchars(format_currency($montoItem)) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">-<?= htmlspecialchars(format_currency($montoItem)) ?></span>
                                    <?php endif; ?>
                                    <?php if ($freq !== 'mensual'): ?>
                                        <div class="text-muted small" style="font-size: 0.68rem; font-weight: normal;">
                                            Eq. <?= htmlspecialchars(format_currency($equivMensual)) ?>/<?= current_lang() === 'es' ? 'mes' : 'mo' ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small" style="font-size: 0.68rem; font-weight: normal;">
                                            / <?= current_lang() === 'es' ? 'mes' : 'mo' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="<?= htmlspecialchars(app_url('pages/fijas.php')) ?>" class="d-inline">
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
                                        data-frecuencia="<?= htmlspecialchars($item['frecuencia'] ?? 'mensual') ?>"
                                        data-dia="<?= (int) $item['dia_pago'] ?>"
                                        data-dia2="<?= (int) ($item['dia_pago_2'] ?? 15) ?>"
                                        data-mes="<?= (int) ($item['mes_pago'] ?? 1) ?>"
                                        data-metodo="<?= htmlspecialchars($item['metodo_pago'] ?? 'debito') ?>"
                                        data-cuenta="<?= (int) ($item['cuenta_id'] ?? 0) ?>"
                                        data-categoria="<?= htmlspecialchars($item['categoria'], ENT_QUOTES) ?>"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="<?= htmlspecialchars(app_url('pages/fijas.php')) ?>" class="d-inline" onsubmit="return confirm('<?= htmlspecialchars(__('delete_confirm'), ENT_QUOTES) ?>');">
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

            <form method="POST" action="<?= htmlspecialchars(app_url('pages/fijas.php')) ?>" novalidate id="newFixedForm">
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
                            <input type="text" class="form-control border-start-0 ps-0" id="fixed_concepto" name="concepto" maxlength="150" placeholder="e.g. Netflix, Spotify, Gym, Rent, Insurance" required>
                        </div>
                    </div>

                    <!-- Frequency (Monthly, Biweekly, Annual) -->
                    <div class="mb-3">
                        <label for="fixed_frecuencia" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_frequency')) ?> <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="fixed_frecuencia" name="frecuencia" required>
                            <option value="mensual" selected><?= htmlspecialchars(__('freq_mensual')) ?></option>
                            <option value="quincenal"><?= htmlspecialchars(__('freq_quincenal')) ?></option>
                            <option value="anual"><?= htmlspecialchars(__('freq_anual')) ?></option>
                        </select>
                    </div>

                    <!-- Amount and Scheduling Dates -->
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

                        <!-- Primary Day of the Month -->
                        <div class="col-6" id="primaryDayCol">
                            <label for="fixed_dia_pago" class="form-label fw-semibold small text-secondary" id="labelDiaPago">
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

                    <!-- Biweekly: Second Day Field (Conditional) -->
                    <div class="mb-3 d-none" id="biweeklyDayGroup">
                        <label for="fixed_dia_pago_2" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_day_2')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-calendar2-day"></i>
                            </span>
                            <input type="number" min="1" max="31" class="form-control border-start-0 ps-0" id="fixed_dia_pago_2" name="dia_pago_2" value="15">
                        </div>
                    </div>

                    <!-- Annual: Month of Billing (Conditional) -->
                    <div class="mb-3 d-none" id="annualMonthGroup">
                        <label for="fixed_mes_pago" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_month_annual')) ?> <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="fixed_mes_pago" name="mes_pago">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>"><?= getMonthName($m) ?></option>
                            <?php endfor; ?>
                        </select>
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
                            <?= htmlspecialchars(__('btn_apply_to_month')) ?>
                        </label>
                    </div>

                </div>

                <div class="modal-footer border-top py-2 px-4">
                    <button type="button" class="btn btn-sm btn-cancel px-3 rounded-pill" data-bs-dismiss="modal">
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

            <form method="POST" action="<?= htmlspecialchars(app_url('pages/fijas.php')) ?>" novalidate id="editFixedForm">
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

                    <!-- Frequency -->
                    <div class="mb-3">
                        <label for="edit_fixed_frecuencia" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_frequency')) ?> <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="edit_fixed_frecuencia" name="frecuencia" required>
                            <option value="mensual"><?= htmlspecialchars(__('freq_mensual')) ?></option>
                            <option value="quincenal"><?= htmlspecialchars(__('freq_quincenal')) ?></option>
                            <option value="anual"><?= htmlspecialchars(__('freq_anual')) ?></option>
                        </select>
                    </div>

                    <!-- Amount and Scheduling Dates -->
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

                    <!-- Biweekly Day 2 (Conditional) -->
                    <div class="mb-3 d-none" id="editBiweeklyDayGroup">
                        <label for="edit_fixed_dia_pago_2" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_day_2')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-calendar2-day"></i>
                            </span>
                            <input type="number" min="1" max="31" class="form-control border-start-0 ps-0" id="edit_fixed_dia_pago_2" name="dia_pago_2" value="15">
                        </div>
                    </div>

                    <!-- Annual Month (Conditional) -->
                    <div class="mb-3 d-none" id="editAnnualMonthGroup">
                        <label for="edit_fixed_mes_pago" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_month_annual')) ?> <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="edit_fixed_mes_pago" name="mes_pago">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>"><?= getMonthName($m) ?></option>
                            <?php endfor; ?>
                        </select>
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
                    <button type="button" class="btn btn-sm btn-cancel px-3 rounded-pill" data-bs-dismiss="modal">
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
    (function () {
        const initFixedPage = function () {
            // Wire up frequency changes for New Fixed modal
            const freqSelect = document.getElementById('fixed_frecuencia');
            const biweeklyGroup = document.getElementById('biweeklyDayGroup');
            const annualGroup = document.getElementById('annualMonthGroup');

            const handleFreqChange = (val, biweeklyEl, annualEl) => {
                if (!biweeklyEl || !annualEl) return;
                if (val === 'quincenal') {
                    biweeklyEl.classList.remove('d-none');
                    annualEl.classList.add('d-none');
                } else if (val === 'anual') {
                    biweeklyEl.classList.add('d-none');
                    annualEl.classList.remove('d-none');
                } else {
                    biweeklyEl.classList.add('d-none');
                    annualEl.classList.add('d-none');
                }
            };

            if (freqSelect && !freqSelect.dataset.bound) {
                freqSelect.dataset.bound = 'true';
                freqSelect.addEventListener('change', function () {
                    handleFreqChange(this.value, biweeklyGroup, annualGroup);
                });
            }

            const editFreqSelect = document.getElementById('edit_fixed_frecuencia');
            const editBiweeklyGroup = document.getElementById('editBiweeklyDayGroup');
            const editAnnualGroup = document.getElementById('editAnnualMonthGroup');

            if (editFreqSelect && !editFreqSelect.dataset.bound) {
                editFreqSelect.dataset.bound = 'true';
                editFreqSelect.addEventListener('change', function () {
                    handleFreqChange(this.value, editBiweeklyGroup, editAnnualGroup);
                });
            }

            // Wire up Edit Fixed Transaction Modal with clicked row data
            const editFixedModal = document.getElementById('editFixedModal');
            if (editFixedModal && !editFixedModal.dataset.bound) {
                editFixedModal.dataset.bound = 'true';
                editFixedModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget ? (event.relatedTarget.closest('[data-bs-toggle="modal"]') || event.relatedTarget) : null;
                    if (!button) return;

                    const id = button.getAttribute('data-id') || '';
                    const tipo = button.getAttribute('data-tipo') || 'expense';
                    const concepto = button.getAttribute('data-concepto') || '';
                    const monto = button.getAttribute('data-monto') || '0';
                    const frecuencia = button.getAttribute('data-frecuencia') || 'mensual';
                    const dia = button.getAttribute('data-dia') || '1';
                    const dia2 = button.getAttribute('data-dia2') || '15';
                    const mes = button.getAttribute('data-mes') || '1';
                    const metodo = button.getAttribute('data-metodo') || 'debito';
                    const cuenta = button.getAttribute('data-cuenta') || '0';
                    const categoria = button.getAttribute('data-categoria') || '';

                    const idEl = editFixedModal.querySelector('#edit_fixed_id');
                    const conceptoEl = editFixedModal.querySelector('#edit_fixed_concepto');
                    const montoEl = editFixedModal.querySelector('#edit_fixed_monto');
                    const freqEl = editFixedModal.querySelector('#edit_fixed_frecuencia');
                    const diaEl = editFixedModal.querySelector('#edit_fixed_dia_pago');
                    const dia2El = editFixedModal.querySelector('#edit_fixed_dia_pago_2');
                    const mesEl = editFixedModal.querySelector('#edit_fixed_mes_pago');
                    const catEl = editFixedModal.querySelector('#edit_fixed_categoria');
                    const metodoEl = editFixedModal.querySelector('#edit_fixed_metodo_pago');
                    const cuentaEl = editFixedModal.querySelector('#edit_fixed_cuenta_id');

                    if (idEl) idEl.value = id;
                    if (conceptoEl) conceptoEl.value = concepto;
                    if (montoEl) {
                        const parsedMonto = parseFloat(monto);
                        montoEl.value = isNaN(parsedMonto) ? '' : parsedMonto.toFixed(2);
                    }
                    if (freqEl) {
                        freqEl.value = frecuencia;
                        handleFreqChange(frecuencia, editBiweeklyGroup, editAnnualGroup);
                    }
                    if (diaEl) diaEl.value = dia;
                    if (dia2El) dia2El.value = dia2;
                    if (mesEl) mesEl.value = mes;
                    if (catEl) catEl.value = categoria;
                    if (metodoEl) metodoEl.value = metodo;
                    if (cuentaEl) cuentaEl.value = cuenta;

                    if (tipo === 'income') {
                        const incRadio = editFixedModal.querySelector('#edit_fixed_type_income');
                        if (incRadio) incRadio.checked = true;
                    } else {
                        const expRadio = editFixedModal.querySelector('#edit_fixed_type_expense');
                        if (expRadio) expRadio.checked = true;
                    }
                });
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initFixedPage);
        } else {
            initFixedPage();
        }
    })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
