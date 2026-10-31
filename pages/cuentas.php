<?php
/**
 * Accounts & Debit/Credit Cards Management
 * Gestor de Presupuesto Personal y Control de Gastos
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/lang.php';

$pdo = getDB();

// -------------------------------------------------------------
// Handle POST Actions (Create, Adjust Balance, Delete)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 1: Create a new account / card
    if ($action === 'create') {
        $nombre       = trim($_POST['nombre'] ?? '');
        $tipo         = trim($_POST['tipo'] ?? 'tarjeta_debito');
        $saldoInicial = trim($_POST['saldo_inicial'] ?? '0.00');

        $allowedTypes = ['tarjeta_debito', 'tarjeta_credito', 'cuenta_bancaria', 'efectivo'];

        if (empty($nombre) || !in_array($tipo, $allowedTypes, true) || !is_numeric($saldoInicial)) {
            set_flash('danger', __('val_required_fields'));
        } else {
            try {
                $saldo = max(0.0, round((float) $saldoInicial, 2));

                // Determine icon and color based on type
                $icono = 'bi-credit-card';
                $color = '#2563eb';
                if ($tipo === 'tarjeta_credito') {
                    $icono = 'bi-credit-card-2-front';
                    $color = '#7c3aed';
                } elseif ($tipo === 'cuenta_bancaria') {
                    $icono = 'bi-bank';
                    $color = '#0284c7';
                } elseif ($tipo === 'efectivo') {
                    $icono = 'bi-cash-stack';
                    $color = '#059669';
                }

                $stmt = $pdo->prepare("
                    INSERT INTO cuentas (nombre, tipo, saldo_actual, color, icono) 
                    VALUES (:nombre, :tipo, :saldo_actual, :color, :icono)
                ");
                $stmt->execute([
                    ':nombre'       => $nombre,
                    ':tipo'         => $tipo,
                    ':saldo_actual' => $saldo,
                    ':color'        => $color,
                    ':icono'        => $icono
                ]);

                $newAccountId = (int) $pdo->lastInsertId();

                // If initial balance > 0, log it in transactions ledger
                if ($saldo > 0) {
                    $logStmt = $pdo->prepare("
                        INSERT INTO transacciones (tipo, concepto, monto, categoria, fecha, cuenta_id, metodo_pago) 
                        VALUES ('income', :concepto, :monto, :categoria, CURRENT_DATE(), :cuenta_id, :metodo_pago)
                    ");
                    $logStmt->execute([
                        ':concepto'    => __('form_initial_balance') . ' - ' . $nombre,
                        ':monto'       => $saldo,
                        ':categoria'   => __('cat_investments'),
                        ':cuenta_id'   => $newAccountId,
                        ':metodo_pago' => $tipo
                    ]);
                }

                set_flash('success', __('msg_account_created'));
            } catch (PDOException $e) {
                error_log("Error creating account: " . $e->getMessage());
                set_flash('danger', __('val_db_error'));
            }
        }
        header("Location: cuentas.php");
        exit;
    }

    // Action 2: Add or Withdraw Balance Manually
    if ($action === 'adjust_balance') {
        $accountId  = filter_var($_POST['cuenta_id'] ?? 0, FILTER_VALIDATE_INT);
        $opType     = trim($_POST['op_type'] ?? 'add'); // 'add' or 'withdraw'
        $monto      = trim($_POST['monto'] ?? '0');
        $concepto   = trim($_POST['concepto'] ?? '');

        if (!$accountId || !is_numeric($monto) || (float) $monto <= 0) {
            set_flash('danger', __('val_invalid_amount'));
        } else {
            try {
                $accStmt = $pdo->prepare("SELECT * FROM cuentas WHERE id = :id");
                $accStmt->execute([':id' => $accountId]);
                $account = $accStmt->fetch();

                if ($account) {
                    $amountFloat = round((float) $monto, 2);

                    if ($opType === 'add') {
                        // Deposit funds
                        $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id")
                            ->execute([':monto' => $amountFloat, ':id' => $accountId]);

                        $txDesc = !empty($concepto) ? $concepto : (__('adjust_add') . ' - ' . $account['nombre']);
                        $pdo->prepare("
                            INSERT INTO transacciones (tipo, concepto, monto, categoria, fecha, cuenta_id, metodo_pago) 
                            VALUES ('income', :concepto, :monto, :categoria, CURRENT_DATE(), :cuenta_id, :metodo_pago)
                        ")->execute([
                            ':concepto'    => $txDesc,
                            ':monto'       => $amountFloat,
                            ':categoria'   => __('cat_other'),
                            ':cuenta_id'   => $accountId,
                            ':metodo_pago' => $account['tipo']
                        ]);
                    } else {
                        // Withdraw funds
                        $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id")
                            ->execute([':monto' => $amountFloat, ':id' => $accountId]);

                        $txDesc = !empty($concepto) ? $concepto : (__('adjust_withdraw') . ' - ' . $account['nombre']);
                        $pdo->prepare("
                            INSERT INTO transacciones (tipo, concepto, monto, categoria, fecha, cuenta_id, metodo_pago) 
                            VALUES ('expense', :concepto, :monto, :categoria, CURRENT_DATE(), :cuenta_id, :metodo_pago)
                        ")->execute([
                            ':concepto'    => $txDesc,
                            ':monto'       => $amountFloat,
                            ':categoria'   => __('cat_other'),
                            ':cuenta_id'   => $accountId,
                            ':metodo_pago' => $account['tipo']
                        ]);
                    }

                    set_flash('success', __('msg_balance_updated'));
                }
            } catch (PDOException $e) {
                error_log("Error adjusting balance: " . $e->getMessage());
                set_flash('danger', __('val_db_error'));
            }
        }
        header("Location: cuentas.php");
        exit;
    }

    // Action 3: Delete Account
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id && $id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM cuentas WHERE id = :id");
                $stmt->execute([':id' => $id]);
                set_flash('success', __('msg_account_deleted'));
            } catch (PDOException $e) {
                error_log("Error deleting account: " . $e->getMessage());
                set_flash('danger', __('msg_error_delete'));
            }
        }
        header("Location: cuentas.php");
        exit;
    }
}

// -------------------------------------------------------------
// Fetch All Accounts with Linked Monthly Fixed Expenses
// -------------------------------------------------------------
$accounts = [];
$totalAllBalances = 0.0;
$totalAllLinkedFixed = 0.0;

try {
    $stmt = $pdo->query("SELECT * FROM cuentas ORDER BY id ASC");
    $rawAccounts = $stmt->fetchAll();

    foreach ($rawAccounts as $acc) {
        $accId = (int) $acc['id'];
        $currentBalance = (float) $acc['saldo_actual'];
        $totalAllBalances += $currentBalance;

        // Fetch monthly active fixed expenses linked to this card/account
        $fixedStmt = $pdo->prepare("
            SELECT id, tipo, concepto, monto, categoria, dia_pago 
            FROM transacciones_fijas 
            WHERE cuenta_id = :cuenta_id AND activo = 1 
            ORDER BY dia_pago ASC
        ");
        $fixedStmt->execute([':cuenta_id' => $accId]);
        $linkedFixedItems = $fixedStmt->fetchAll();

        $linkedExpenses = 0.0;
        $linkedIncome   = 0.0;
        foreach ($linkedFixedItems as $item) {
            if ($item['tipo'] === 'expense') {
                $linkedExpenses += (float) $item['monto'];
            } elseif ($item['tipo'] === 'income') {
                $linkedIncome += (float) $item['monto'];
            }
        }

        $totalAllLinkedFixed += $linkedExpenses;

        // Projected remaining balance on this card/account after covering linked fixed monthly expenses
        $remainingBalance = $currentBalance - $linkedExpenses + $linkedIncome;

        $acc['linked_fixed_items'] = $linkedFixedItems;
        $acc['linked_expenses']    = $linkedExpenses;
        $acc['linked_income']      = $linkedIncome;
        $acc['remaining_balance']  = $remainingBalance;

        $accounts[] = $acc;
    }
} catch (PDOException $e) {
    error_log("Error fetching accounts: " . $e->getMessage());
}

$pageTitle = __('accounts_title');
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Section -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 text-slate-900"><?= htmlspecialchars(__('accounts_title')) ?></h1>
        <p class="text-secondary mb-0 small"><?= htmlspecialchars(__('accounts_subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm px-3 py-2 rounded-pill" data-bs-toggle="modal" data-bs-target="#newAccountModal">
            <i class="bi bi-plus-circle-fill"></i>
            <span><?= htmlspecialchars(__('btn_new_account')) ?></span>
        </button>
    </div>
</div>

<!-- Total Summary Across All Accounts Banner -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card card-stat h-100 p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-uppercase fw-semibold text-secondary small"><?= htmlspecialchars(__('total_liquid_balance')) ?></span>
                <span class="icon-bubble icon-bubble-balance">
                    <i class="bi bi-wallet2"></i>
                </span>
            </div>
            <div class="h2 fw-bold text-slate-900 mb-0">
                <?= htmlspecialchars(format_currency($totalAllBalances)) ?>
            </div>
            <div class="small text-muted mt-1">
                <i class="bi bi-credit-card-2-front me-1"></i><?= count($accounts) ?> <?= current_lang() === 'es' ? 'cuentas y tarjetas activas' : 'active accounts & cards' ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card card-stat h-100 p-3 border-start border-4 border-danger">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-uppercase fw-semibold text-secondary small"><?= htmlspecialchars(__('card_linked_monthly_expenses')) ?> (Total)</span>
                <span class="icon-bubble icon-bubble-expense">
                    <i class="bi bi-arrow-repeat"></i>
                </span>
            </div>
            <div class="h2 fw-bold text-danger mb-0">
                -<?= htmlspecialchars(format_currency($totalAllLinkedFixed)) ?>
            </div>
            <div class="small text-muted mt-1">
                <i class="bi bi-calendar-month me-1"></i><?= current_lang() === 'es' ? 'Compromisos mensuales recurrentes asignados' : 'Monthly recurring commitments assigned' ?>
            </div>
        </div>
    </div>
</div>

<!-- Accounts & Cards Grid -->
<?php if (empty($accounts)): ?>
    <div class="card border-0 shadow-sm rounded-4 text-center py-5 px-3">
        <div class="icon-bubble icon-bubble-balance mb-3 mx-auto" style="width: 64px; height: 64px; font-size: 1.8rem;">
            <i class="bi bi-credit-card-2-front"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars(__('empty_accounts_title')) ?></h5>
        <p class="text-secondary small mb-4 mx-auto" style="max-width: 460px;">
            <?= htmlspecialchars(__('empty_accounts_desc')) ?>
        </p>
        <div>
            <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#newAccountModal">
                <i class="bi bi-plus-lg me-1"></i><?= htmlspecialchars(__('btn_new_account')) ?>
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($accounts as $acc): ?>
            <?php 
                $badgeTypeClass = 'bg-primary-subtle text-primary border-primary-subtle';
                $typeName = __('type_cuenta_bancaria');
                if ($acc['tipo'] === 'tarjeta_debito') {
                    $badgeTypeClass = 'bg-info-subtle text-info-emphasis border-info-subtle';
                    $typeName = __('type_tarjeta_debito');
                } elseif ($acc['tipo'] === 'tarjeta_credito') {
                    $badgeTypeClass = 'bg-purple-subtle text-purple border-purple-subtle';
                    $typeName = __('type_tarjeta_credito');
                } elseif ($acc['tipo'] === 'efectivo') {
                    $badgeTypeClass = 'bg-success-subtle text-success border-success-subtle';
                    $typeName = __('type_efectivo');
                }
            ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card card-stat h-100 border-0 shadow-sm rounded-4 overflow-hidden d-flex flex-column">
                    
                    <!-- Card Top Accent Bar -->
                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width: 34px; height: 34px; background-color: <?= htmlspecialchars($acc['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($acc['icono']) ?>"></i>
                            </span>
                            <div>
                                <h6 class="fw-bold mb-0 text-slate-800"><?= htmlspecialchars($acc['nombre']) ?></h6>
                                <span class="badge border <?= $badgeTypeClass ?>" style="font-size: 0.68rem;">
                                    <?= htmlspecialchars($typeName) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Dropdown Actions -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border-0 rounded-circle text-muted" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 30px; height: 30px;">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li>
                                    <button type="button" class="dropdown-item small d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#adjustModal" data-acc-id="<?= (int) $acc['id'] ?>" data-acc-name="<?= htmlspecialchars($acc['nombre'], ENT_QUOTES) ?>" data-op="add">
                                        <i class="bi bi-plus-circle text-success"></i> <?= htmlspecialchars(__('btn_add_balance')) ?>
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item small d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#adjustModal" data-acc-id="<?= (int) $acc['id'] ?>" data-acc-name="<?= htmlspecialchars($acc['nombre'], ENT_QUOTES) ?>" data-op="withdraw">
                                        <i class="bi bi-dash-circle text-danger"></i> <?= htmlspecialchars(__('btn_withdraw_balance')) ?>
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="cuentas.php" onsubmit="return confirm('<?= htmlspecialchars(__('delete_confirm'), ENT_QUOTES) ?>');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $acc['id'] ?>">
                                        <button type="submit" class="dropdown-item small text-danger d-flex align-items-center gap-2">
                                            <i class="bi bi-trash3"></i> <?= htmlspecialchars(__('btn_delete')) ?>
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Card Body: Current Balance & Remaining Balance -->
                    <div class="card-body p-3 flex-grow-1">
                        
                        <!-- Current Balance -->
                        <div class="mb-3">
                            <span class="text-uppercase text-muted fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                <?= htmlspecialchars(__('card_current_balance')) ?>
                            </span>
                            <div class="h3 fw-bold text-slate-900 mb-0">
                                <?= htmlspecialchars(format_currency((float) $acc['saldo_actual'])) ?>
                            </div>
                        </div>

                        <!-- Linked Monthly Commitments Calculation -->
                        <div class="p-2.5 bg-light rounded-3 border mb-3">
                            <div class="d-flex justify-content-between align-items-center small text-secondary mb-1">
                                <span><i class="bi bi-arrow-repeat me-1 text-danger"></i><?= htmlspecialchars(__('card_linked_monthly_expenses')) ?>:</span>
                                <span class="fw-semibold text-danger">-<?= htmlspecialchars(format_currency((float) $acc['linked_expenses'])) ?></span>
                            </div>
                            <hr class="my-1.5 opacity-25">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-semibold text-dark">
                                    <i class="bi bi-calculator me-1 text-primary"></i><?= htmlspecialchars(__('card_remaining_balance')) ?>:
                                </span>
                                <span class="fw-bold <?= ($acc['remaining_balance'] >= 0) ? 'text-primary' : 'text-danger' ?>">
                                    <?= htmlspecialchars(format_currency((float) $acc['remaining_balance'])) ?>
                                </span>
                            </div>
                            <div class="text-muted" style="font-size: 0.68rem;">
                                <?= htmlspecialchars(__('card_remaining_balance_help')) ?>
                            </div>
                        </div>

                        <!-- Linked Subscriptions Preview -->
                        <div>
                            <span class="text-muted small fw-semibold d-block mb-1" style="font-size: 0.75rem;">
                                <?= htmlspecialchars(__('linked_fixed_items')) ?> (<?= count($acc['linked_fixed_items']) ?>):
                            </span>
                            <?php if (empty($acc['linked_fixed_items'])): ?>
                                <p class="text-muted small mb-0 fst-italic" style="font-size: 0.78rem;">
                                    <?= htmlspecialchars(__('no_linked_fixed_items')) ?>
                                </p>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-1">
                                    <?php foreach ($acc['linked_fixed_items'] as $item): ?>
                                        <div class="d-flex justify-content-between align-items-center small bg-white px-2 py-1 rounded border">
                                            <span class="text-truncate text-slate-700" style="max-width: 160px;">
                                                <?= htmlspecialchars($item['concepto']) ?>
                                            </span>
                                            <span class="fw-semibold text-danger small">
                                                -<?= htmlspecialchars(format_currency((float) $item['monto'])) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Card Quick Action Buttons -->
                    <div class="card-footer bg-white border-top p-2.5 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success flex-grow-1 rounded-pill d-inline-flex align-items-center justify-content-center gap-1" data-bs-toggle="modal" data-bs-target="#adjustModal" data-acc-id="<?= (int) $acc['id'] ?>" data-acc-name="<?= htmlspecialchars($acc['nombre'], ENT_QUOTES) ?>" data-op="add">
                            <i class="bi bi-plus-lg"></i>
                            <span><?= htmlspecialchars(__('btn_add_balance')) ?></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger flex-grow-1 rounded-pill d-inline-flex align-items-center justify-content-center gap-1" data-bs-toggle="modal" data-bs-target="#adjustModal" data-acc-id="<?= (int) $acc['id'] ?>" data-acc-name="<?= htmlspecialchars($acc['nombre'], ENT_QUOTES) ?>" data-op="withdraw">
                            <i class="bi bi-dash-lg"></i>
                            <span><?= htmlspecialchars(__('btn_withdraw_balance')) ?></span>
                        </button>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal: New Account / Card -->
<div class="modal fade" id="newAccountModal" tabindex="-1" aria-labelledby="newAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-bubble icon-bubble-balance" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-credit-card-2-front"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-slate-800" id="newAccountModalLabel"><?= htmlspecialchars(__('btn_new_account')) ?></h5>
                        <small class="text-secondary"><?= htmlspecialchars(__('form_account_name')) ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="cuentas.php" novalidate>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <!-- Account Name -->
                    <div class="mb-3">
                        <label for="acc_nombre" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_account_name')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-credit-card"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" id="acc_nombre" name="nombre" maxlength="100" placeholder="<?= htmlspecialchars(__('form_placeholder_account_name')) ?>" required>
                        </div>
                    </div>

                    <!-- Account Type -->
                    <div class="mb-3">
                        <label for="acc_tipo" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_account_type')) ?> <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="acc_tipo" name="tipo" required>
                            <option value="tarjeta_debito"><?= htmlspecialchars(__('type_tarjeta_debito')) ?></option>
                            <option value="tarjeta_credito"><?= htmlspecialchars(__('type_tarjeta_credito')) ?></option>
                            <option value="cuenta_bancaria"><?= htmlspecialchars(__('type_cuenta_bancaria')) ?></option>
                            <option value="efectivo"><?= htmlspecialchars(__('type_efectivo')) ?></option>
                        </select>
                    </div>

                    <!-- Initial Balance -->
                    <div class="mb-3">
                        <label for="acc_saldo_inicial" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_initial_balance')) ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0 fw-bold">$</span>
                            <input type="number" step="0.01" min="0" class="form-control border-start-0 ps-0" id="acc_saldo_inicial" name="saldo_inicial" value="0.00" required>
                        </div>
                        <div class="form-text small">
                            <?= current_lang() === 'es' ? 'Puedes iniciar con saldo 0.00 o con el dinero que tengas actualmente en la cuenta.' : 'You can start with 0.00 or with your current available funds.' ?>
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

<!-- Modal: Adjust Balance (Add / Withdraw Funds) -->
<div class="modal fade" id="adjustModal" tabindex="-1" aria-labelledby="adjustModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-bubble icon-bubble-balance" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-arrow-left-right"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-slate-800" id="adjustModalTitle"><?= htmlspecialchars(__('btn_adjust_balance')) ?></h5>
                        <small class="text-secondary" id="adjustModalAccountName"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="cuentas.php" novalidate>
                <input type="hidden" name="action" value="adjust_balance">
                <input type="hidden" name="cuenta_id" id="adjust_cuenta_id" value="">

                <div class="modal-body p-4">
                    <!-- Operation Type (Add vs Withdraw) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_adjust_type')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="op_type" id="op_add" value="add" checked>
                                <label class="btn btn-outline-success w-100 py-2 d-flex align-items-center justify-content-center gap-2 rounded-3" for="op_add">
                                    <i class="bi bi-plus-circle"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars(__('adjust_add')) ?></span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="op_type" id="op_withdraw" value="withdraw">
                                <label class="btn btn-outline-danger w-100 py-2 d-flex align-items-center justify-content-center gap-2 rounded-3" for="op_withdraw">
                                    <i class="bi bi-dash-circle"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars(__('adjust_withdraw')) ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="mb-3">
                        <label for="adjust_monto" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_amount')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0 fw-bold">$</span>
                            <input type="number" step="0.01" min="0.01" class="form-control border-start-0 ps-0" id="adjust_monto" name="monto" placeholder="0.00" required>
                        </div>
                    </div>

                    <!-- Note / Concept -->
                    <div class="mb-3">
                        <label for="adjust_concepto" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('adjust_concept')) ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-card-text"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" id="adjust_concepto" name="concepto" maxlength="150" placeholder="<?= htmlspecialchars(__('adjust_placeholder_concept')) ?>">
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
    // Wire up adjust balance modal with dynamic account and operation
    const adjustModal = document.getElementById('adjustModal');
    if (adjustModal) {
        adjustModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const accId = button.getAttribute('data-acc-id');
            const accName = button.getAttribute('data-acc-name');
            const op = button.getAttribute('data-op') || 'add';

            adjustModal.querySelector('#adjust_cuenta_id').value = accId;
            adjustModal.querySelector('#adjustModalAccountName').textContent = accName;

            if (op === 'withdraw') {
                adjustModal.querySelector('#op_withdraw').checked = true;
            } else {
                adjustModal.querySelector('#op_add').checked = true;
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
