<?php
/**
 * Footer Template Component
 * Gestor de Presupuesto Personal y Control de Gastos
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/lang.php';
?>
        </div> <!-- End .container -->
    </main> <!-- End main -->

    <!-- Modal for Delete Confirmation -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars(__('btn_delete')) ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-secondary mb-1">
                        <?= htmlspecialchars(__('delete_confirm')) ?>
                    </p>
                    <div class="p-2 mt-2 bg-light rounded border text-muted small" id="deleteModalConcept"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">
                        <?= htmlspecialchars(__('btn_cancel')) ?>
                    </button>
                    <form id="deleteForm" method="POST" action="eliminar.php" class="d-inline">
                        <input type="hidden" name="id" id="deleteModalId" value="">
                        <button type="submit" class="btn btn-sm btn-danger px-3">
                            <i class="bi bi-trash3-fill me-1"></i>
                            <?= htmlspecialchars(__('btn_delete')) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Footer -->
    <footer class="footer-custom py-3 mt-auto">
        <div class="container d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div>
                <span class="fw-semibold text-dark"><?= htmlspecialchars(__('app_name')) ?></span>
                <span class="mx-1">&bull;</span>
                <span><?= htmlspecialchars(__('footer_text')) ?></span>
            </div>
            <div class="small">
                &copy; <?= date('Y') ?> <?= htmlspecialchars(__('all_rights_reserved')) ?>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
        // Set up the delete confirmation modal dynamically
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const txId = button.getAttribute('data-tx-id');
                const txConcept = button.getAttribute('data-tx-concept');
                
                const inputId = deleteModal.querySelector('#deleteModalId');
                const conceptPreview = deleteModal.querySelector('#deleteModalConcept');

                inputId.value = txId;
                conceptPreview.textContent = txConcept || ('ID #' + txId);
            });
        }
    </script>
</body>
</html>
