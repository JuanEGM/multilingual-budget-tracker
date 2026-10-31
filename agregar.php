<?php
/**
 * Add New Transaction Form & Handler
 * Gestor de Presupuesto Personal y Control de Gastos - Phase 1
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/lang.php';

$pdo = getDB();

$errors = [];
$formData = [
    'tipo'      => 'expense', // sensible default for everyday expenses
    'concepto'  => '',
    'monto'     => '',
    'categoria' => '',
    'fecha'     => date('Y-m-d')
];

// -------------------------------------------------------------
// Handle POST Form Submission
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and trim inputs
    $formData['tipo']      = trim($_POST['tipo'] ?? '');
    $formData['concepto']  = trim($_POST['concepto'] ?? '');
    $formData['monto']     = trim($_POST['monto'] ?? '');
    $formData['categoria'] = trim($_POST['categoria'] ?? '');
    $formData['fecha']     = trim($_POST['fecha'] ?? '');

    // Validation 1: Required fields
    if (
        empty($formData['tipo']) || 
        empty($formData['concepto']) || 
        $formData['monto'] === '' || 
        empty($formData['categoria']) || 
        empty($formData['fecha'])
    ) {
        $errors[] = __('val_required_fields');
    }

    // Validation 2: Type must be either income or expense
    if (!in_array($formData['tipo'], ['income', 'expense'], true)) {
        $errors[] = __('val_invalid_type');
    }

    // Validation 3: Concept length
    if (mb_strlen($formData['concepto']) > 150) {
        $errors[] = __('val_concept_len');
    }

    // Validation 4: Category length
    if (mb_strlen($formData['categoria']) > 50) {
        $errors[] = __('val_category_len');
    }

    // Validation 5: Amount numeric and positive
    if (!is_numeric($formData['monto']) || (float) $formData['monto'] <= 0) {
        $errors[] = __('val_invalid_amount');
    }

    // Validation 6: Date validation (YYYY-MM-DD)
    $dateParts = explode('-', $formData['fecha']);
    if (
        count($dateParts) !== 3 || 
        !checkdate((int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0])
    ) {
        $errors[] = __('val_invalid_date');
    }

    // If no validation errors, proceed with DB insertion using prepared statement
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO transacciones (tipo, concepto, monto, categoria, fecha) 
                VALUES (:tipo, :concepto, :monto, :categoria, :fecha)
            ");

            $stmt->execute([
                ':tipo'      => $formData['tipo'],
                ':concepto'  => $formData['concepto'],
                ':monto'     => round((float) $formData['monto'], 2),
                ':categoria' => $formData['categoria'],
                ':fecha'     => $formData['fecha']
            ]);

            set_flash('success', __('msg_success_add'));
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            error_log("Database Insert Error: " . $e->getMessage());
            $errors[] = __('val_db_error');
        }
    }
}

// Preset standard categories for convenience
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
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        
        <!-- Navigation breadcrumb / Back link -->
        <div class="mb-3">
            <a href="index.php" class="text-decoration-none text-secondary small d-inline-flex align-items-center gap-1">
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

                <form method="POST" action="agregar.php" novalidate>
                    
                    <!-- Transaction Type (Radio Buttons styled as Segments) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_type')) ?> <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input 
                                    type="radio" 
                                    class="btn-check" 
                                    name="tipo" 
                                    id="type_income" 
                                    value="income" 
                                    <?= ($formData['tipo'] === 'income') ? 'checked' : '' ?>
                                >
                                <label class="btn btn-outline-success w-100 py-2 d-flex align-items-center justify-content-center gap-2 rounded-3" for="type_income">
                                    <i class="bi bi-arrow-down-left"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars(__('type_income')) ?></span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input 
                                    type="radio" 
                                    class="btn-check" 
                                    name="tipo" 
                                    id="type_expense" 
                                    value="expense" 
                                    <?= ($formData['tipo'] === 'expense') ? 'checked' : '' ?>
                                >
                                <label class="btn btn-outline-danger w-100 py-2 d-flex align-items-center justify-content-center gap-2 rounded-3" for="type_expense">
                                    <i class="bi bi-arrow-up-right"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars(__('type_expense')) ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Concept / Description -->
                    <div class="mb-3">
                        <label for="concepto" class="form-label fw-semibold small text-secondary">
                            <?= htmlspecialchars(__('form_field_concept')) ?> <span class="text-danger">*</span>
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
                                required
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

                    <!-- Category (With Datalist for typing or quick picking) -->
                    <div class="mb-4">
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
                                required
                            >
                            <datalist id="categoriesList">
                                <?php foreach ($presetCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-text small">
                            <?= htmlspecialchars(__('form_select_category')) ?>
                        </div>
                    </div>

                    <!-- Actions Buttons -->
                    <div class="d-flex justify-content-end align-items-center gap-2 pt-2 border-top">
                        <a href="index.php" class="btn btn-light px-4 text-secondary rounded-pill">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
