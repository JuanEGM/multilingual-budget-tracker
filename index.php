<?php
/**
 * Dashboard & Transactions Overview
 * Gestor de Presupuesto Personal y Control de Gastos - Phase 1+
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/lang.php';

$pdo = getDB();

// -------------------------------------------------------------
// 1. Fetch Financial Totals (Actual Recorded Ledger)
// -------------------------------------------------------------
$totalIncome = 0.0;
$totalExpense = 0.0;

try {
    $totalsStmt = $pdo->query("
        SELECT tipo, COALESCE(SUM(monto), 0) AS subtotal 
        FROM transacciones 
        GROUP BY tipo
    ");
    $totals = $totalsStmt->fetchAll();

    foreach ($totals as $row) {
        if ($row['tipo'] === 'income') {
            $totalIncome = (float) $row['subtotal'];
        } elseif ($row['tipo'] === 'expense') {
            $totalExpense = (float) $row['subtotal'];
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching totals: " . $e->getMessage());
}

$netBalance = $totalIncome - $totalExpense;

// -------------------------------------------------------------
// 2. Fetch Active Fixed / Subscriptions Summary
// -------------------------------------------------------------
$totalFixedMonthly = 0.0;
$fixedCount = 0;
try {
    $fixedStmt = $pdo->query("
        SELECT COUNT(*) as cnt, COALESCE(SUM(monto), 0) as total_fixed 
        FROM transacciones_fijas 
        WHERE activo = 1 AND tipo = 'expense'
    ");
    $fixedData = $fixedStmt->fetch();
    $fixedCount = (int) ($fixedData['cnt'] ?? 0);
    $totalFixedMonthly = (float) ($fixedData['total_fixed'] ?? 0);
} catch (PDOException $e) {
    error_log("Error fetching fixed totals: " . $e->getMessage());
}

// -------------------------------------------------------------
// 3. Fetch Accounts Summary
// -------------------------------------------------------------
$totalAccountBalances = 0.0;
$accountCount = 0;
try {
    $accStmt = $pdo->query("SELECT COUNT(*) as cnt, COALESCE(SUM(saldo_actual), 0) as total_balance FROM cuentas");
    $accData = $accStmt->fetch();
    $accountCount = (int) ($accData['cnt'] ?? 0);
    $totalAccountBalances = (float) ($accData['total_balance'] ?? 0);
} catch (PDOException $e) {
    error_log("Error fetching accounts: " . $e->getMessage());
}

// -------------------------------------------------------------
// 4. Fetch All Transactions with Account Names
// -------------------------------------------------------------
$transactions = [];
try {
    $stmt = $pdo->query("
        SELECT t.id, t.tipo, t.concepto, t.monto, t.categoria, t.fecha, t.es_fija, t.fija_id, t.cuenta_id, t.metodo_pago,
               c.nombre AS nombre_cuenta, c.tipo AS tipo_cuenta
        FROM transacciones t 
        LEFT JOIN cuentas c ON t.cuenta_id = c.id
        ORDER BY t.fecha DESC, t.id DESC
    ");
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching transactions: " . $e->getMessage());
}

$pageTitle = __('nav_dashboard');
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Section -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 text-slate-900"><?= htmlspecialchars(__('nav_dashboard')) ?></h1>
        <p class="text-secondary mb-0 small"><?= htmlspecialchars(__('table_subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="cuentas.php" class="btn btn-outline-primary d-inline-flex align-items-center gap-2 shadow-sm px-3 py-2 rounded-pill">
            <i class="bi bi-credit-card-2-front"></i>
            <span><?= htmlspecialchars(__('nav_accounts')) ?> (<?= htmlspecialchars(format_currency($totalAccountBalances)) ?>)</span>
        </a>
        <a href="fijas.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm px-3 py-2 rounded-pill">
            <i class="bi bi-arrow-repeat text-purple"></i>
            <span><?= htmlspecialchars(__('nav_recurring')) ?> (<?= $fixedCount ?>)</span>
        </a>
        <a href="agregar.php" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm px-3 py-2 rounded-pill">
            <i class="bi bi-plus-circle-fill"></i>
            <span><?= htmlspecialchars(__('nav_new_tx')) ?></span>
        </a>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="row g-3 mb-4">
    <!-- Total Income Card -->
    <div class="col-12 col-md-4">
        <div class="card card-stat h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold text-secondary small"><?= htmlspecialchars(__('card_total_income')) ?></span>
                <span class="icon-bubble icon-bubble-income">
                    <i class="bi bi-arrow-down-left"></i>
                </span>
            </div>
            <div class="h2 fw-bold text-success mb-1">
                <?= htmlspecialchars(format_currency($totalIncome)) ?>
            </div>
            <div class="small text-muted d-flex align-items-center gap-1">
                <i class="bi bi-shield-check text-success"></i>
                <span><?= htmlspecialchars(__('type_income')) ?></span>
            </div>
        </div>
    </div>

    <!-- Total Expenses Card -->
    <div class="col-12 col-md-4">
        <div class="card card-stat h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold text-secondary small"><?= htmlspecialchars(__('card_total_expense')) ?></span>
                <span class="icon-bubble icon-bubble-expense">
                    <i class="bi bi-arrow-up-right"></i>
                </span>
            </div>
            <div class="h2 fw-bold text-danger mb-1">
                <?= htmlspecialchars(format_currency($totalExpense)) ?>
            </div>
            <div class="small text-muted d-flex align-items-center gap-1">
                <i class="bi bi-shield-exclamation text-danger"></i>
                <span><?= htmlspecialchars(__('type_expense')) ?></span>
            </div>
        </div>
    </div>

    <!-- Net Balance Card -->
    <div class="col-12 col-md-4">
        <div class="card card-stat h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold text-secondary small"><?= htmlspecialchars(__('card_net_balance')) ?></span>
                <span class="icon-bubble icon-bubble-balance">
                    <i class="bi bi-wallet2"></i>
                </span>
            </div>
            <div class="h2 fw-bold mb-1 <?= ($netBalance >= 0) ? 'text-primary' : 'text-danger' ?>">
                <?= htmlspecialchars(format_currency($netBalance)) ?>
            </div>
            <div class="small d-flex align-items-center gap-1">
                <?php if ($netBalance > 0): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-graph-up-arrow me-1"></i><?= htmlspecialchars(__('card_balance_pos')) ?>
                    </span>
                <?php elseif ($netBalance < 0): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                        <i class="bi bi-graph-down-arrow me-1"></i><?= htmlspecialchars(__('card_balance_neg')) ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border">
                        <?= htmlspecialchars(__('card_balance_zero')) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recurring Commitment Insight Banner -->
<?php if ($fixedCount > 0): ?>
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white border-start border-4 border-primary">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="icon-bubble icon-bubble-purple" style="width: 42px; height: 42px; font-size: 1.2rem;">
                    <i class="bi bi-arrow-repeat"></i>
                </span>
                <div>
                    <h6 class="fw-bold mb-0 text-slate-800">
                        <?= htmlspecialchars(__('recurring_title')) ?>: 
                        <span class="text-primary"><?= htmlspecialchars(format_currency($totalFixedMonthly)) ?></span>
                        <span class="text-muted small fw-normal">/ <?= current_lang() === 'es' ? 'mes en compromisos fijos' : 'month in fixed commitments' ?></span>
                    </h6>
                    <small class="text-secondary">
                        <?= current_lang() === 'es' ? 'Tienes ' . $fixedCount . ' suscripciones o pagos periódicos activos configurados.' : 'You have ' . $fixedCount . ' active subscriptions or scheduled payments.' ?>
                    </small>
                </div>
            </div>
            <a href="fijas.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 align-self-start align-self-sm-center">
                <?= current_lang() === 'es' ? 'Ver Suscripciones' : 'View Subscriptions' ?> <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Transactions Table Section -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
        <div>
            <h5 class="fw-bold mb-0 text-slate-800"><?= htmlspecialchars(__('table_title')) ?></h5>
            <small class="text-muted"><?= count($transactions) ?> <?= strtolower(htmlspecialchars(__('table_title'))) ?></small>
        </div>
        <?php if (!empty($transactions)): ?>
            <a href="agregar.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="bi bi-plus-lg me-1"></i><?= htmlspecialchars(__('nav_new_tx')) ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <?php if (empty($transactions)): ?>
            <!-- Empty State -->
            <div class="text-center py-5 px-3">
                <div class="icon-bubble icon-bubble-balance mb-3" style="width: 64px; height: 64px; font-size: 1.8rem;">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars(__('empty_title')) ?></h5>
                <p class="text-secondary small mb-4 mx-auto" style="max-width: 420px;">
                    <?= htmlspecialchars(__('empty_subtitle')) ?>
                </p>
                <a href="agregar.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i><?= htmlspecialchars(__('btn_add_first')) ?>
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-custom table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 5%;"><?= htmlspecialchars(__('col_id')) ?></th>
                            <th style="width: 14%;"><?= htmlspecialchars(__('col_date')) ?></th>
                            <th style="width: 28%;"><?= htmlspecialchars(__('col_concept')) ?></th>
                            <th style="width: 15%;"><?= htmlspecialchars(__('col_category')) ?></th>
                            <th style="width: 13%;"><?= htmlspecialchars(__('col_type')) ?></th>
                            <th class="text-end" style="width: 15%;"><?= htmlspecialchars(__('col_amount')) ?></th>
                            <th class="text-center pe-4" style="width: 10%;"><?= htmlspecialchars(__('col_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="ps-4 text-muted small fw-mono">
                                    #<?= (int) $tx['id'] ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-calendar3 text-secondary small"></i>
                                        <span class="small fw-medium"><?= htmlspecialchars(format_date($tx['fecha'])) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold text-slate-800">
                                            <?= htmlspecialchars($tx['concepto']) ?>
                                        </span>
                                        <?php if (!empty($tx['es_fija'])): ?>
                                            <span class="badge-recurring-pill d-inline-flex align-items-center gap-1" title="<?= htmlspecialchars(__('badge_recurring')) ?>">
                                                <i class="bi bi-arrow-repeat"></i>
                                                <?= htmlspecialchars(__('badge_recurring')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($tx['nombre_cuenta'])): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-credit-card me-1 text-primary"></i><?= htmlspecialchars($tx['nombre_cuenta']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-category">
                                        <i class="bi bi-tag-fill me-1 text-secondary opacity-75"></i><?= htmlspecialchars($tx['categoria']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($tx['tipo'] === 'income'): ?>
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
                                    <?php if ($tx['tipo'] === 'income'): ?>
                                        <span class="text-success">+<?= htmlspecialchars(format_currency((float) $tx['monto'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">-<?= htmlspecialchars(format_currency((float) $tx['monto'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-danger border-0 rounded-circle" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal"
                                        data-tx-id="<?= (int) $tx['id'] ?>"
                                        data-tx-concept="<?= htmlspecialchars($tx['concepto'], ENT_QUOTES) ?>"
                                        title="<?= htmlspecialchars(__('btn_delete')) ?>"
                                        style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;"
                                    >
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
