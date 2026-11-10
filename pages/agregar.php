<?php
/**
 * Add New Transaction & Internal Transfer Form & Handler
 * Gestor de Presupuesto Personal y Control de Gastos - Phase 2
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/lang.php';

$pdo = getDB();

$errors = [];
$formData = [
    'tipo'              => 'expense', // 'expense', 'income', 'transfer'
    'concepto'          => '',
    'monto'             => '',
    'categoria'         => '',
    'fecha'             => date('Y-m-d'),
    'metodo_pago'       => 'debito',
    'cuenta_id'         => '0',
    'cuenta_destino_id' => '0',
    'es_recurrente'     => '',
    'frecuencia'        => 'mensual'
];

// -------------------------------------------------------------
// Fetch Available Accounts / Cards for Linking
// -------------------------------------------------------------
$availableAccounts = [];
try {
    $accStmt = $pdo->query("SELECT id, nombre, tipo, saldo_actual FROM cuentas ORDER BY nombre ASC");
    $availableAccounts = $accStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching accounts: " . $e->getMessage());
}

// -------------------------------------------------------------
// Handle POST Form Submission
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and trim inputs
    $formData['tipo']              = trim($_POST['tipo'] ?? 'expense');
    $formData['concepto']          = trim($_POST['concepto'] ?? '');
    $formData['monto']             = trim($_POST['monto'] ?? '');
    $formData['categoria']         = trim($_POST['categoria'] ?? '');
    $formData['fecha']             = trim($_POST['fecha'] ?? '');
    $formData['metodo_pago']       = trim($_POST['metodo_pago'] ?? 'debito');
    $formData['cuenta_id']         = trim($_POST['cuenta_id'] ?? '0');
    $formData['cuenta_destino_id'] = trim($_POST['cuenta_destino_id'] ?? '0');
    $formData['es_recurrente']     = !empty($_POST['es_recurrente']) ? '1' : '';
    $formData['frecuencia']        = trim($_POST['frecuencia'] ?? 'mensual');

    $cuentaIdInt = filter_var($formData['cuenta_id'], FILTER_VALIDATE_INT);
    if ($cuentaIdInt === false || $cuentaIdInt <= 0) {
        $cuentaIdInt = null;
    }

    $cuentaDestinoIdInt = filter_var($formData['cuenta_destino_id'], FILTER_VALIDATE_INT);
    if ($cuentaDestinoIdInt === false || $cuentaDestinoIdInt <= 0) {
        $cuentaDestinoIdInt = null;
    }

    $validTypes = ['income', 'expense', 'transfer'];
    if (!in_array($formData['tipo'], $validTypes, true)) {
        $errors[] = __('val_invalid_type');
    }

    // Default concept & category for transfers if blank
    if ($formData['tipo'] === 'transfer') {
        if (empty($formData['categoria'])) {
            $formData['categoria'] = __('cat_transfer');
        }
        if (!$cuentaIdInt || !$cuentaDestinoIdInt) {
            $errors[] = __('val_required_fields');
        } elseif ($cuentaIdInt === $cuentaDestinoIdInt) {
            $errors[] = __('val_same_account');
        }
    } else {
        if (empty($formData['categoria'])) {
            $errors[] = __('val_required_fields');
        }
    }

    if ($formData['monto'] === '' || empty($formData['fecha'])) {
        $errors[] = __('val_required_fields');
    }

    if (!is_numeric($formData['monto']) || (float) $formData['monto'] <= 0) {
        $errors[] = __('val_invalid_amount');
    }

    if (mb_strlen($formData['concepto']) > 150) {
        $errors[] = __('val_concept_len');
    }

    if (mb_strlen($formData['categoria']) > 50) {
        $errors[] = __('val_category_len');
    }

    $dateParts = explode('-', $formData['fecha']);
    if (
        count($dateParts) !== 3 || 
        !checkdate((int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0])
    ) {
        $errors[] = __('val_invalid_date');
    }

    // If no validation errors, proceed with DB insertion
    if (empty($errors)) {
        try {
            $amountFloat = round((float) $formData['monto'], 2);

            $pdo->beginTransaction();

            if ($formData['tipo'] === 'transfer') {
                // Fetch account names for fallback concept
                $acc1Stmt = $pdo->prepare("SELECT nombre, tipo FROM cuentas WHERE id = :id");
                $acc1Stmt->execute([':id' => $cuentaIdInt]);
                $acc1 = $acc1Stmt->fetch();

                $acc2Stmt = $pdo->prepare("SELECT nombre FROM cuentas WHERE id = :id");
                $acc2Stmt->execute([':id' => $cuentaDestinoIdInt]);
                $acc2 = $acc2Stmt->fetch();

                if (empty($formData['concepto'])) {
                    $formData['concepto'] = __('transfer_concept_default', [
                        ':from' => $acc1['nombre'] ?? 'Origen',
                        ':to'   => $acc2['nombre'] ?? 'Destino'
                    ]);
                }

                // Deduct from source account
                $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id")
                    ->execute([':monto' => $amountFloat, ':id' => $cuentaIdInt]);

                // Add to destination account
                $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id")
                    ->execute([':monto' => $amountFloat, ':id' => $cuentaDestinoIdInt]);

                // Insert into transactions ledger
                $stmt = $pdo->prepare("
                    INSERT INTO transacciones (tipo, concepto, monto, categoria, fecha, es_fija, fija_id, cuenta_id, cuenta_destino_id, metodo_pago) 
                    VALUES ('transfer', :concepto, :monto, :categoria, :fecha, 0, NULL, :cuenta_id, :cuenta_destino_id, :metodo_pago)
                ");
                $stmt->execute([
                    ':concepto'          => $formData['concepto'],
                    ':monto'             => $amountFloat,
                    ':categoria'         => $formData['categoria'],
                    ':fecha'             => $formData['fecha'],
                    ':cuenta_id'         => $cuentaIdInt,
                    ':cuenta_destino_id' => $cuentaDestinoIdInt,
                    ':metodo_pago'       => $acc1['tipo'] ?? 'transferencia'
                ]);

                $pdo->commit();
                set_flash('success', __('msg_transfer_success'));
                header("Location: " . app_url('index.php'));
                exit;
            } else {
                // Income or Expense
                $fijaId = null;
                $esFija = 0;

                if (!empty($formData['es_recurrente'])) {
                    $dayOfMonth = (int) date('j', strtotime($formData['fecha']));
                    $freq = in_array($formData['frecuencia'], ['mensual', 'quincenal', 'anual'], true) ? $formData['frecuencia'] : 'mensual';
                    $dia2 = ($freq === 'quincenal') ? min(31, $dayOfMonth + 15) : null;
                    $mesAnual = (int) date('n', strtotime($formData['fecha']));

                    $stmtFija = $pdo->prepare("
                        INSERT INTO transacciones_fijas (tipo, concepto, monto, categoria, frecuencia, dia_pago, dia_pago_2, mes_pago, metodo_pago, cuenta_id, activo, ultima_generacion) 
                        VALUES (:tipo, :concepto, :monto, :categoria, :frecuencia, :dia_pago, :dia_pago_2, :mes_pago, :metodo_pago, :cuenta_id, 1, :ultima_generacion)
                    ");
                    $stmtFija->execute([
                        ':tipo'              => $formData['tipo'],
                        ':concepto'          => $formData['concepto'],
                        ':monto'             => $amountFloat,
                        ':categoria'         => $formData['categoria'],
                        ':frecuencia'        => $freq,
                        ':dia_pago'          => $dayOfMonth,
                        ':dia_pago_2'        => $dia2,
                        ':mes_pago'          => $mesAnual,
                        ':metodo_pago'       => $formData['metodo_pago'],
                        ':cuenta_id'         => $cuentaIdInt,
                        ':ultima_generacion' => $formData['fecha']
                    ]);
                    $fijaId = (int) $pdo->lastInsertId();
                    $esFija = 1;
                }

                // Insert into main transactions ledger
                $stmt = $pdo->prepare("
                    INSERT INTO transacciones (tipo, concepto, monto, categoria, fecha, es_fija, fija_id, cuenta_id, metodo_pago) 
                    VALUES (:tipo, :concepto, :monto, :categoria, :fecha, :es_fija, :fija_id, :cuenta_id, :metodo_pago)
                ");
                $stmt->execute([
                    ':tipo'        => $formData['tipo'],
                    ':concepto'    => $formData['concepto'],
                    ':monto'       => $amountFloat,
                    ':categoria'   => $formData['categoria'],
                    ':fecha'       => $formData['fecha'],
                    ':es_fija'     => $esFija,
                    ':fija_id'     => $fijaId,
                    ':cuenta_id'   => $cuentaIdInt,
                    ':metodo_pago' => $formData['metodo_pago']
                ]);

                // Update account balance if an account is linked
                if ($cuentaIdInt) {
                    if ($formData['tipo'] === 'expense') {
                        $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id")
                            ->execute([':monto' => $amountFloat, ':id' => $cuentaIdInt]);
                    } else {
                        $pdo->prepare("UPDATE cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id")
                            ->execute([':monto' => $amountFloat, ':id' => $cuentaIdInt]);
                    }
                }

                $pdo->commit();
                set_flash('success', __('msg_success_add'));
                header("Location: " . app_url('index.php'));
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Database Insert Error: " . $e->getMessage());
            $errors[] = __('val_db_error');
        }
    }
}

// Preset standard categories
$presetCategories = [
    __('cat_food'),
    __('cat_salary'),
    __('cat_housing'),
    __('cat_utilities'),
    __('cat_transport'),
    __('cat_entertainment'),
    __('cat_freelance'),
    __('cat_health'),
    __('cat_investments'),
    __('cat_education'),
    __('cat_other')
];

$pageTitle = __('form_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center animate-form-entrance">
    <div class="col-12 col-md-8 col-lg-6">
        
        <!-- Navigation breadcrumb / Back link -->
        <div class="mb-3">
            <a href="<?= htmlspecialchars(app_url('index.php')) ?>" class="text-decoration-none text-secondary small d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i>
                <span><?= htmlspecialchars(__('btn_back')) ?></span>
            </a>
        </div>

        <!-- Form Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-bubble icon-bubble-balance" style="width: 38px; height: 38px; font-size: 1.1rem;">
                        <i class="bi bi-pencil-square"></i>
                    </span>
                    <div>
                        <h5 class="fw-bold mb-0 text-slate-800"><?= htmlspecialchars(__('form_title')) ?></h5>
                        <small class="text-secondary"><?= htmlspecialchars(__('form_subtitle')) ?></small>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                
                <!-- Validation Errors Alert -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <strong class="small text-uppercase"><?= htmlspecialchars(__('val_required_fields')) ?></strong>
                        </div>
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars(app_url('pages/agregar.php')) ?>" novalidate id="txForm">
                    
                    <!-- Transaction Type (Expense, Income, Transfer) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_type')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input 
                                    type="radio" 
                                    class="btn-check" 
                                    name="tipo" 
                                    id="type_expense" 
                                    value="expense" 
                                    <?= ($formData['tipo'] === 'expense') ? 'checked' : '' ?>
                                >
                                <label class="btn btn-outline-danger w-100 py-2 d-flex flex-column align-items-center justify-content-center gap-1 rounded-3" for="type_expense">
                                    <i class="bi bi-arrow-up-right"></i>
                                    <span class="fw-semibold small"><?= htmlspecialchars(__('type_expense')) ?></span>
                                </label>
                            </div>
                            <div class="col-4">
                                <input 
                                    type="radio" 
                                    class="btn-check" 
                                    name="tipo" 
                                    id="type_income" 
                                    value="income" 
                                    <?= ($formData['tipo'] === 'income') ? 'checked' : '' ?>
                                >
                                <label class="btn btn-outline-success w-100 py-2 d-flex flex-column align-items-center justify-content-center gap-1 rounded-3" for="type_income">
                                    <i class="bi bi-arrow-down-left"></i>
                                    <span class="fw-semibold small"><?= htmlspecialchars(__('type_income')) ?></span>
                                </label>
                            </div>
                            <div class="col-4">
                                <input 
                                    type="radio" 
                                    class="btn-check" 
                                    name="tipo" 
                                    id="type_transfer" 
                                    value="transfer" 
                                    <?= ($formData['tipo'] === 'transfer') ? 'checked' : '' ?>
                                >
                                <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center justify-content-center gap-1 rounded-3" for="type_transfer">
                                    <i class="bi bi-arrow-left-right"></i>
                                    <span class="fw-semibold small"><?= htmlspecialchars(__('type_transfer')) ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Transfer Accounts Group (Visible only when 'transfer' is selected) -->
                    <div id="transferAccountsGroup" class="<?= ($formData['tipo'] === 'transfer') ? '' : 'd-none' ?>">
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-sm-5">
                                    <label for="transfer_cuenta_origen" class="form-label fw-semibold small text-secondary">
                                        <?= htmlspecialchars(__('form_source_account')) ?> <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="transfer_cuenta_origen" name="cuenta_id">
                                        <option value="0"><?= htmlspecialchars(__('form_select_source')) ?></option>
                                        <?php foreach ($availableAccounts as $acc): ?>
                                            <option value="<?= (int) $acc['id'] ?>" <?= ((string)$formData['cuenta_id'] === (string)$acc['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($acc['nombre']) ?> (<?= htmlspecialchars(format_currency((float) $acc['saldo_actual'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-2 text-center pt-sm-3">
                                    <i class="bi bi-arrow-right text-primary fs-5 d-none d-sm-inline"></i>
                                    <i class="bi bi-arrow-down text-primary fs-5 d-inline d-sm-none"></i>
                                </div>
                                <div class="col-12 col-sm-5">
                                    <label for="transfer_cuenta_destino" class="form-label fw-semibold small text-secondary">
                                        <?= htmlspecialchars(__('form_destination_account')) ?> <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="transfer_cuenta_destino" name="cuenta_destino_id">
                                        <option value="0"><?= htmlspecialchars(__('form_select_destination')) ?></option>
                                        <?php foreach ($availableAccounts as $acc): ?>
                                            <option value="<?= (int) $acc['id'] ?>" <?= ((string)$formData['cuenta_destino_id'] === (string)$acc['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($acc['nombre']) ?> (<?= htmlspecialchars(format_currency((float) $acc['saldo_actual'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Regular Payment Method & Link to Account (Visible for Expense / Income) -->
                    <div id="regularAccountGroup" class="<?= ($formData['tipo'] === 'transfer') ? 'd-none' : '' ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label for="metodo_pago" class="form-label fw-semibold small text-secondary">
                                    <?= htmlspecialchars(__('form_field_payment_method')) ?> <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="metodo_pago" name="metodo_pago">
                                    <option value="debito" <?= ($formData['metodo_pago'] === 'debito') ? 'selected' : '' ?>><?= htmlspecialchars(__('method_debito')) ?></option>
                                    <option value="credito" <?= ($formData['metodo_pago'] === 'credito') ? 'selected' : '' ?>><?= htmlspecialchars(__('method_credito')) ?></option>
                                    <option value="transferencia" <?= ($formData['metodo_pago'] === 'transferencia') ? 'selected' : '' ?>><?= htmlspecialchars(__('method_transferencia')) ?></option>
                                    <option value="efectivo" <?= ($formData['metodo_pago'] === 'efectivo') ? 'selected' : '' ?>><?= htmlspecialchars(__('method_efectivo')) ?></option>
                                </select>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="regular_cuenta_id" class="form-label fw-semibold small text-secondary">
                                    <?= htmlspecialchars(__('form_field_link_account')) ?>
                                </label>
                                <select class="form-select" id="regular_cuenta_id" name="regular_cuenta_id">
                                    <option value="0"><?= htmlspecialchars(__('option_no_account')) ?></option>
                                    <?php foreach ($availableAccounts as $acc): ?>
                                        <option value="<?= (int) $acc['id'] ?>" <?= ((string)$formData['cuenta_id'] === (string)$acc['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($acc['nombre']) ?> (<?= htmlspecialchars(format_currency((float) $acc['saldo_actual'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Concept / Description -->
                    <div class="mb-3">
                        <label for="concepto" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_concept')) ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-card-text"></i>
                            </span>
                            <input 
                                type="text" 
                                class="form-control border-start-0 ps-0" 
                                id="concepto" 
                                name="concepto" 
                                maxlength="150" 
                                placeholder="<?= htmlspecialchars(__('form_placeholder_concept')) ?>" 
                                value="<?= htmlspecialchars($formData['concepto']) ?>"
                            >
                        </div>
                    </div>

                    <!-- Amount & Date (Grid Row) -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="monto" class="form-label fw-semibold small text-secondary">
                                <?= htmlspecialchars(__('form_field_amount')) ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary border-end-0 fw-bold">$</span>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    min="0.01" 
                                    class="form-control border-start-0 ps-0" 
                                    id="monto" 
                                    name="monto" 
                                    placeholder="0.00" 
                                    value="<?= htmlspecialchars($formData['monto']) ?>" 
                                    required
                                >
                            </div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label for="fecha" class="form-label fw-semibold small text-secondary">
                                <?= htmlspecialchars(__('form_field_date')) ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary border-end-0">
                                    <i class="bi bi-calendar-event"></i>
                                </span>
                                <input 
                                    type="date" 
                                    class="form-control border-start-0 ps-0" 
                                    id="fecha" 
                                    name="fecha" 
                                    value="<?= htmlspecialchars($formData['fecha']) ?>" 
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Category (Hidden if Transfer) -->
                    <div class="mb-3" id="categoryGroup">
                        <label for="categoria" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_category')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <i class="bi bi-tag"></i>
                            </span>
                            <input 
                                class="form-control border-start-0 ps-0" 
                                list="categoriesList" 
                                id="categoria" 
                                name="categoria" 
                                maxlength="50" 
                                placeholder="<?= htmlspecialchars(__('form_select_category')) ?>" 
                                value="<?= htmlspecialchars($formData['categoria']) ?>"
                            >
                            <datalist id="categoriesList">
                                <?php foreach ($presetCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <!-- Recurring Subscription Switch (Hidden if Transfer) -->
                    <div id="recurringSection" class="<?= ($formData['tipo'] === 'transfer') ? 'd-none' : '' ?>">
                        <div class="form-check form-switch p-3 mb-3 bg-light rounded-3 border">
                            <input 
                                class="form-check-input ms-0 me-2" 
                                type="checkbox" 
                                role="switch" 
                                id="es_recurrente" 
                                name="es_recurrente" 
                                value="1" 
                                <?= (!empty($formData['es_recurrente'])) ? 'checked' : '' ?>
                            >
                            <label class="form-check-label fw-semibold small" for="es_recurrente">
                                <i class="bi bi-arrow-repeat text-purple me-1"></i>
                                <?= htmlspecialchars(__('form_save_as_recurring')) ?>
                            </label>
                            <div class="text-muted small ms-4">
                                <?= htmlspecialchars(__('form_save_as_recurring_help')) ?>
                            </div>

                            <div class="mt-3 ms-4 <?= (!empty($formData['es_recurrente'])) ? '' : 'd-none' ?>" id="recurringFreqPicker">
                                <label for="frecuencia" class="form-label fw-semibold small text-secondary">
                                    <?= htmlspecialchars(__('form_field_frequency')) ?>
                                </label>
                                <select class="form-select form-select-sm" id="frecuencia" name="frecuencia">
                                    <option value="mensual" <?= ($formData['frecuencia'] === 'mensual') ? 'selected' : '' ?>><?= htmlspecialchars(__('freq_mensual')) ?></option>
                                    <option value="quincenal" <?= ($formData['frecuencia'] === 'quincenal') ? 'selected' : '' ?>><?= htmlspecialchars(__('freq_quincenal')) ?></option>
                                    <option value="anual" <?= ($formData['frecuencia'] === 'anual') ? 'selected' : '' ?>><?= htmlspecialchars(__('freq_anual')) ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Buttons -->
                    <div class="d-flex justify-content-end align-items-center gap-2 pt-2 border-top">
                        <a href="<?= htmlspecialchars(app_url('index.php')) ?>" class="btn btn-cancel px-4 rounded-pill">
                            <?= htmlspecialchars(__('btn_cancel')) ?>
                        </a>
                        <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm d-inline-flex align-items-center gap-2">
                            <i class="bi bi-check2-circle"></i>
                            <span><?= htmlspecialchars(__('btn_save')) ?></span>
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>

<script>
    (function () {
        const initAgregarPage = function () {
            const typeRadios = document.querySelectorAll('input[name="tipo"]');
            const transferGroup = document.getElementById('transferAccountsGroup');
            const regularGroup = document.getElementById('regularAccountGroup');
            const recurringSection = document.getElementById('recurringSection');
            const categoryGroup = document.getElementById('categoryGroup');
            const regularAccSelect = document.getElementById('regular_cuenta_id');
            const transferSourceSelect = document.getElementById('transfer_cuenta_origen');

            const updateTypeUI = (type) => {
                if (type === 'transfer') {
                    if (transferGroup) transferGroup.classList.remove('d-none');
                    if (regularGroup) regularGroup.classList.add('d-none');
                    if (recurringSection) recurringSection.classList.add('d-none');
                    if (categoryGroup) categoryGroup.classList.add('d-none');
                } else {
                    if (transferGroup) transferGroup.classList.add('d-none');
                    if (regularGroup) regularGroup.classList.remove('d-none');
                    if (recurringSection) recurringSection.classList.remove('d-none');
                    if (categoryGroup) categoryGroup.classList.remove('d-none');
                }
            };

            typeRadios.forEach(radio => {
                if (radio.dataset.bound) return;
                radio.dataset.bound = 'true';
                radio.addEventListener('change', function () {
                    updateTypeUI(this.value);
                });
            });

            // Sync regular account selection with transfer source if switched
            if (regularAccSelect && transferSourceSelect && !regularAccSelect.dataset.bound) {
                regularAccSelect.dataset.bound = 'true';
                regularAccSelect.addEventListener('change', function () {
                    if (this.value) {
                        transferSourceSelect.value = this.value;
                    }
                });
            }

            // Toggle frequency picker inside recurring switch
            const recurringSwitch = document.getElementById('es_recurrente');
            const freqPicker = document.getElementById('recurringFreqPicker');
            if (recurringSwitch && freqPicker && !recurringSwitch.dataset.bound) {
                recurringSwitch.dataset.bound = 'true';
                recurringSwitch.addEventListener('change', function () {
                    if (this.checked) {
                        freqPicker.classList.remove('d-none');
                    } else {
                        freqPicker.classList.add('d-none');
                    }
                });
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAgregarPage);
        } else {
            initAgregarPage();
        }
    })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
