<?php
/**
 * Footer & Layout Close Template - Fintech SaaS Edition
 * Gestor de Presupuesto Personal y Control de Gastos
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/lang.php';
?>
            </div> <!-- Fin .container-fluid -->
        </main> <!-- Fin .app-viewport -->

        <!-- Barra de Estado Inferior Minimalista (Desktop Statusbar) -->
        <footer class="app-statusbar d-flex align-items-center justify-content-between px-3 py-1">
            <div class="d-flex align-items-center gap-2">
                <span class="text-primary">&bull;</span>
                <span class="text-secondary"><?= htmlspecialchars(__('app_name')) ?></span>
                <span class="text-muted d-none d-md-inline">&bull; <?= htmlspecialchars(__('footer_text')) ?></span>
            </div>
            <div class="tabular-nums text-muted">
                &copy; <?= date('Y') ?> &bull; <span class="text-secondary"><?= htmlspecialchars(__('all_rights_reserved')) ?></span>
            </div>
        </footer>

    </div> <!-- Fin .app-main -->
</div> <!-- Fin .app-shell -->

<!-- Modal de Confirmación de Eliminación (SaaS Theme) -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="background-color: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); border-radius: 14px;">
            <div class="modal-header border-0 pb-0 pt-3 px-3">
                <h6 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteModalLabel">
                    <i data-lucide="alert-triangle" style="width: 17px; height: 17px;"></i>
                    <span><?= htmlspecialchars(__('btn_delete')) ?></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-2 px-3">
                <p class="text-secondary mb-2 small">
                    <?= htmlspecialchars(__('delete_confirm')) ?>
                </p>
                <div class="p-2 rounded border border-secondary-subtle small fw-mono text-truncate" style="background: var(--bg-surface-subtle); color: var(--text-primary);" id="deleteModalConcept"></div>
            </div>
            <div class="modal-footer border-0 pt-0 px-3 pb-3 d-flex gap-2">
                <button type="button" class="btn btn-cancel btn-sm flex-fill rounded-2" data-bs-dismiss="modal">
                    <?= htmlspecialchars(__('btn_cancel')) ?>
                </button>
                <form id="deleteForm" method="POST" action="<?= htmlspecialchars(app_url('controllers/eliminar.php')) ?>" class="flex-fill m-0">
                    <input type="hidden" name="id" id="deleteModalId" value="">
                    <button type="submit" class="btn btn-danger btn-sm w-100 rounded-2 fw-medium">
                        <?= htmlspecialchars(__('btn_delete')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Fintech App Interactions & Theme Engine -->
<script src="<?= htmlspecialchars(app_url('assets/js/fintech-app.js')) ?>"></script>
</body>
</html>
