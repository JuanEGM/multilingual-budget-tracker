/**
 * Fintech App Client Interactions, Theme Engine & Ultra-Fluid SPA Navigation
 * Gestor Financiero - Personal Budget & Expense Manager
 */

// Immediate Theme Application (prevents flash of white/dark on initial load)
(function () {
    const savedTheme = localStorage.getItem('fintech_theme') || 'dark';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
})();

document.addEventListener('DOMContentLoaded', () => {
    // -------------------------------------------------------------
    // 1. Initialize Lucide Icons Helper
    // -------------------------------------------------------------
    const renderIcons = () => {
        if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }
    };
    renderIcons();

    // -------------------------------------------------------------
    // 2. Light / Dark Mode Switcher Engine (Instantáneo por CSS)
    // -------------------------------------------------------------
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const THEME_KEY      = 'fintech_theme';

    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem(THEME_KEY, theme);

        if (themeToggleBtn) {
            themeToggleBtn.setAttribute('title', theme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        }
    };

    const currentTheme = localStorage.getItem(THEME_KEY) || 'dark';
    applyTheme(currentTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const activeTheme = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);
        });
    }

    // -------------------------------------------------------------
    // 3. Robust Sidebar Toggle Engine (Desktop & Mobile)
    // -------------------------------------------------------------
    const appShell            = document.getElementById('appShell');
    const appSidebar          = document.getElementById('appSidebar');
    const globalSidebarToggle = document.getElementById('globalSidebarToggle');
    const sidebarCollapseBtn  = document.getElementById('sidebarCollapseBtn');
    const SIDEBAR_STORAGE_KEY = 'fintech_sidebar_collapsed';

    // Restore desktop collapsed preference
    if (localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'true' && window.innerWidth >= 992) {
        if (appShell) appShell.classList.add('sidebar-collapsed');
        document.documentElement.classList.add('sidebar-collapsed');
        if (sidebarCollapseBtn) {
            sidebarCollapseBtn.setAttribute('title', 'Expandir barra lateral');
        }
    }

    const toggleSidebar = () => {
        if (window.innerWidth < 992) {
            if (appSidebar) {
                appSidebar.classList.toggle('mobile-open');
            }
        } else {
            if (appShell) {
                const isCollapsed = appShell.classList.toggle('sidebar-collapsed');
                document.documentElement.classList.toggle('sidebar-collapsed', isCollapsed);
                localStorage.setItem(SIDEBAR_STORAGE_KEY, isCollapsed ? 'true' : 'false');
                if (sidebarCollapseBtn) {
                    sidebarCollapseBtn.setAttribute('title', isCollapsed ? 'Expandir barra lateral' : 'Colapsar barra lateral');
                }
            }
        }
    };

    if (globalSidebarToggle) {
        globalSidebarToggle.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSidebar();
        });
    }

    if (sidebarCollapseBtn) {
        sidebarCollapseBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSidebar();
        });
    }

    document.addEventListener('click', (e) => {
        if (window.innerWidth >= 992) return;
        if (!appSidebar || !appSidebar.classList.contains('mobile-open')) return;

        if (!appSidebar.contains(e.target) && !globalSidebarToggle?.contains(e.target)) {
            appSidebar.classList.remove('mobile-open');
        }
    });

    // -------------------------------------------------------------
    // 4. Ubicación de Modales en el Body (Previene bloqueo por Backdrop)
    // -------------------------------------------------------------
    const relocateModalsToBody = () => {
        // Mueve cualquier modal del viewport directamente al <body>
        // para que no quede atrapado detrás del modal-backdrop con z-index incorrecto
        document.querySelectorAll('.app-shell .modal, .app-viewport .modal').forEach(modal => {
            document.body.appendChild(modal);
        });
    };

    // -------------------------------------------------------------
    // 5. Component Initializers (Modales, Formularios y AJAX)
    // -------------------------------------------------------------

    // Modal de confirmación de eliminación
    const initDeleteModal = (root = document) => {
        const deleteModal = root.querySelector ? (root.querySelector('#deleteModal') || document.getElementById('deleteModal')) : document.getElementById('deleteModal');
        if (!deleteModal || deleteModal.dataset.bound === 'true') return;
        deleteModal.dataset.bound = 'true';

        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget ? (event.relatedTarget.closest('[data-bs-toggle="modal"]') || event.relatedTarget) : null;
            if (!button) return;

            const txId = button.getAttribute('data-tx-id') || '';
            const txConcept = button.getAttribute('data-tx-concept') || ('ID #' + txId);

            const inputId = deleteModal.querySelector('#deleteModalId');
            const conceptPreview = deleteModal.querySelector('#deleteModalConcept');

            if (inputId) inputId.value = txId;
            if (conceptPreview) conceptPreview.textContent = txConcept;

            renderIcons();
        });
    };

    // Modal de ajuste de saldo (Cuentas)
    const initAdjustModal = (root = document) => {
        const adjustModal = root.querySelector ? (root.querySelector('#adjustModal') || document.getElementById('adjustModal')) : document.getElementById('adjustModal');
        if (!adjustModal || adjustModal.dataset.bound === 'true') return;
        adjustModal.dataset.bound = 'true';

        adjustModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget ? (event.relatedTarget.closest('[data-bs-toggle="modal"]') || event.relatedTarget) : null;
            if (!button) return;

            const accId = button.getAttribute('data-acc-id') || '';
            const accName = button.getAttribute('data-acc-name') || '';
            const op = button.getAttribute('data-op') || 'add';

            const idInput = adjustModal.querySelector('#adjust_cuenta_id');
            const namePreview = adjustModal.querySelector('#adjustModalAccountName');
            const opWithdraw = adjustModal.querySelector('#op_withdraw');
            const opAdd = adjustModal.querySelector('#op_add');

            if (idInput) idInput.value = accId;
            if (namePreview) namePreview.textContent = accName;

            if (op === 'withdraw') {
                if (opWithdraw) opWithdraw.checked = true;
            } else {
                if (opAdd) opAdd.checked = true;
            }
        });
    };

    // Modal de edición de transacción fija (Fijas)
    const initEditFixedModal = (root = document) => {
        const editFixedModal = root.querySelector ? (root.querySelector('#editFixedModal') || document.getElementById('editFixedModal')) : document.getElementById('editFixedModal');
        if (!editFixedModal || editFixedModal.dataset.bound === 'true') return;
        editFixedModal.dataset.bound = 'true';

        editFixedModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget ? (event.relatedTarget.closest('[data-bs-toggle="modal"]') || event.relatedTarget) : null;
            if (!button) return;

            const id = button.getAttribute('data-id') || '';
            const tipo = button.getAttribute('data-tipo') || 'expense';
            const concepto = button.getAttribute('data-concepto') || '';
            const monto = button.getAttribute('data-monto') || '0';
            const dia = button.getAttribute('data-dia') || '1';
            const metodo = button.getAttribute('data-metodo') || 'debito';
            const cuenta = button.getAttribute('data-cuenta') || '0';
            const categoria = button.getAttribute('data-categoria') || '';

            const idEl = editFixedModal.querySelector('#edit_fixed_id');
            const conceptoEl = editFixedModal.querySelector('#edit_fixed_concepto');
            const montoEl = editFixedModal.querySelector('#edit_fixed_monto');
            const diaEl = editFixedModal.querySelector('#edit_fixed_dia_pago');
            const catEl = editFixedModal.querySelector('#edit_fixed_categoria');
            const metodoEl = editFixedModal.querySelector('#edit_fixed_metodo_pago');
            const cuentaEl = editFixedModal.querySelector('#edit_fixed_cuenta_id');

            if (idEl) idEl.value = id;
            if (conceptoEl) conceptoEl.value = concepto;
            if (montoEl) {
                const parsedMonto = parseFloat(monto);
                montoEl.value = isNaN(parsedMonto) ? '' : parsedMonto.toFixed(2);
            }
            if (diaEl) diaEl.value = dia;
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
    };

    // Selector de idioma mediante AJAX
    const initLangSwitchers = (root = document) => {
        root.querySelectorAll('.lang-switch-form').forEach(form => {
            if (form.dataset.bound === 'true') return;
            form.dataset.bound = 'true';

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);
                const targetAction = this.getAttribute('action');

                fetch(targetAction, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        window.location.reload();
                    } else {
                        this.submit();
                    }
                })
                .catch(() => {
                    this.submit();
                });
            });
        });
    };

    // Disparador unificado de componentes de página
    const initPageFeatures = (root = document) => {
        renderIcons();
        initDeleteModal(root);
        initAdjustModal(root);
        initEditFixedModal(root);
        initLangSwitchers(root);
    };

    // Inicializar modales en el body y componentes en la carga directa inicial
    relocateModalsToBody();
    initPageFeatures(document);

    // -------------------------------------------------------------
    // 6. Motor de Navegación SPA Ultra Fluida (Zero-Flicker Turbo Router)
    // -------------------------------------------------------------
    const pageCache = new Map();
    let currentAbortController = null;

    const normalizeUrlPath = (urlStr) => {
        try {
            const u = new URL(urlStr, window.location.href);
            let path = u.pathname.replace(/\/index\.php$/i, '/');
            if (!path.endsWith('/')) {
                path += '/';
            }
            return u.origin + path + u.search;
        } catch (e) {
            return urlStr;
        }
    };

    const getScriptBase = (urlStr) => {
        try {
            const u = new URL(urlStr, window.location.href);
            const path = u.pathname.replace(/\/+$/, '');
            const filename = path.split('/').pop() || 'index.php';
            if (!filename.endsWith('.php')) {
                return 'index.php';
            }
            return filename;
        } catch (e) {
            return 'index.php';
        }
    };

    const updateActiveSidebarLinks = (targetUrl) => {
        const targetScript = getScriptBase(targetUrl);
        document.querySelectorAll('.sidebar-nav-link').forEach(link => {
            const linkScript = getScriptBase(link.href);
            if (linkScript === targetScript) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    };

    const prefetchPage = (url) => {
        try {
            const targetUrl = new URL(url, window.location.href);
            if (targetUrl.origin !== window.location.origin) return;
            if (!targetUrl.pathname.endsWith('.php') && !targetUrl.pathname.endsWith('/')) return;
            if (targetUrl.pathname.includes('/controllers/')) return;
            if (normalizeUrlPath(targetUrl.href) === normalizeUrlPath(window.location.href)) return;
            if (pageCache.has(targetUrl.href)) return;

            fetch(targetUrl.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => {
                if (!res.ok) throw new Error('Bad response');
                return res.text();
            })
            .then(html => {
                pageCache.set(targetUrl.href, html);
            })
            .catch(() => {});
        } catch (err) {}
    };

    document.addEventListener('mouseover', (e) => {
        const link = e.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (link.target === '_blank' || link.hasAttribute('download') || link.dataset.noPjax === 'true') return;
        prefetchPage(link.href);
    }, { passive: true });

    document.addEventListener('touchstart', (e) => {
        const link = e.target.closest('a');
        if (link && link.href) {
            prefetchPage(link.href);
        }
    }, { passive: true });

    const navigateTo = async (url, pushHistory = true) => {
        const targetUrl = new URL(url, window.location.href);
        const viewport = document.getElementById('appViewport');
        const progressBar = document.getElementById('navProgressBar');
        const topbarCurrentPage = document.getElementById('topbarCurrentPage');

        if (!viewport) {
            window.location.href = targetUrl.href;
            return;
        }

        if (currentAbortController) {
            currentAbortController.abort();
        }
        currentAbortController = new AbortController();

        if (appSidebar && appSidebar.classList.contains('mobile-open')) {
            appSidebar.classList.remove('mobile-open');
        }

        if (progressBar) {
            progressBar.style.width = '30%';
            progressBar.classList.remove('done');
            progressBar.classList.add('loading');
            setTimeout(() => {
                if (progressBar && progressBar.classList.contains('loading')) {
                    progressBar.style.width = '75%';
                }
            }, 60);
        }

        viewport.classList.add('is-navigating-out');

        try {
            let html = pageCache.get(targetUrl.href);

            if (!html) {
                const response = await fetch(targetUrl.href, {
                    signal: currentAbortController.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Error al cargar la página: ' + response.status);
                html = await response.text();
                pageCache.set(targetUrl.href, html);
            }

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newViewport = doc.getElementById('appViewport');

            if (!newViewport) {
                window.location.href = targetUrl.href;
                return;
            }

            await new Promise(r => setTimeout(r, 40));

            // 1. Título
            if (doc.title) {
                document.title = doc.title;
            }

            // 2. Breadcrumb
            const newBreadcrumb = doc.getElementById('topbarCurrentPage');
            if (topbarCurrentPage && newBreadcrumb) {
                topbarCurrentPage.textContent = newBreadcrumb.textContent;
            }

            // 3. Enlace activo en sidebar
            updateActiveSidebarLinks(targetUrl.href);

            // 4. Limpiar modales abiertos y eliminar modales dinámicos de la página previa
            document.querySelectorAll('.modal.show').forEach(m => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const instance = bootstrap.Modal.getInstance(m);
                    if (instance) instance.hide();
                }
            });
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');

            // Eliminar modales de la página anterior fijados al body (conservando el global #deleteModal)
            document.querySelectorAll('body > .modal:not(#deleteModal)').forEach(m => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const instance = bootstrap.Modal.getInstance(m);
                    if (instance) instance.dispose();
                }
                m.remove();
            });

            // 5. Inyectar nuevo contenido
            viewport.innerHTML = newViewport.innerHTML;
            viewport.scrollTop = 0;

            // 6. Mover nuevos modales directamente a document.body para evitar trampas de z-index
            relocateModalsToBody();

            // 7. Animación de entrada
            viewport.classList.remove('is-navigating-out');
            viewport.classList.add('is-navigating-in');
            setTimeout(() => {
                viewport.classList.remove('is-navigating-in');
            }, 300);

            // 8. Actualizar historial
            if (pushHistory) {
                window.history.pushState({ url: targetUrl.href }, '', targetUrl.href);
            }

            // 9. Re-inicializar componentes
            initPageFeatures(document);

            // 10. Scripts en línea
            const scripts = viewport.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });

            // 11. Finalizar barra de progreso
            if (progressBar) {
                progressBar.style.width = '100%';
                progressBar.classList.remove('loading');
                progressBar.classList.add('done');
                setTimeout(() => {
                    progressBar.classList.remove('done');
                    progressBar.style.width = '0%';
                }, 300);
            }
        } catch (err) {
            if (err.name === 'AbortError') return;
            console.warn('Fallback a recarga tradicional:', err);
            window.location.href = targetUrl.href;
        }
    };

    document.addEventListener('click', (e) => {
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
        if (link.hasAttribute('download') || link.getAttribute('target') === '_blank') return;
        if (link.dataset.noPjax === 'true') return;

        const targetUrl = new URL(href, window.location.href);

        if (targetUrl.origin !== window.location.origin) return;
        if (targetUrl.pathname.includes('/controllers/')) return;
        if (targetUrl.pathname.match(/\.(bat|sql|zip|pdf|png|jpg|jpeg|gif|svg)$/i)) return;

        // Si es la misma página (ej. / vs /index.php), evitar recarga innecesaria
        if (normalizeUrlPath(targetUrl.href) === normalizeUrlPath(window.location.href)) {
            e.preventDefault();
            const vp = document.getElementById('appViewport');
            if (vp) {
                vp.scrollTo({ top: 0, behavior: 'smooth' });
            }
            return;
        }

        e.preventDefault();
        navigateTo(targetUrl.href, true);
    });

    window.addEventListener('popstate', () => {
        navigateTo(window.location.href, false);
    });
});
