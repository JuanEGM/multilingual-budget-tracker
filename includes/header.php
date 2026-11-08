<?php
/**
 * Header & Sidebar Layout Template - Fintech SaaS Edition
 * Gestor de Presupuesto Personal y Control de Gastos
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/lang.php';

$activeLang   = current_lang();
$currentPage  = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$flashMessage = get_flash();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($activeLang) ?>" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? __('app_name')) ?> — <?= htmlspecialchars(__('app_subtitle')) ?></title>
    
    <!-- Script inmediato de precarga (Anti-FOUC): aplica tema y estado del sidebar antes del render -->
    <script>
        (function() {
            // 1. Evitar animaciones de transición indeseadas durante la carga inicial
            document.documentElement.classList.add('no-transitions');
            window.addEventListener('DOMContentLoaded', function() {
                requestAnimationFrame(function() {
                    document.documentElement.classList.remove('no-transitions');
                });
            });

            // 2. Aplicar tema de color
            const savedTheme = localStorage.getItem('fintech_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);

            // 3. Aplicar estado colapsado antes de que el navegador pinte el DOM
            const isCollapsed = localStorage.getItem('fintech_sidebar_collapsed') === 'true';
            if (isCollapsed && window.innerWidth >= 992) {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        })();
    </script>

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Bootstrap Icons 1.11.3 & Lucide Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Fintech & Modern SaaS Design System -->
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/fintech-theme.css')) ?>">
</head>
<body>

<div class="app-shell" id="appShell">
    <script>
        if (localStorage.getItem('fintech_sidebar_collapsed') === 'true' && window.innerWidth >= 992) {
            document.getElementById('appShell').classList.add('sidebar-collapsed');
        }
    </script>

    <!-- ========================================================================
         BARRA LATERAL VERTICAL (SIDEBAR)
         ======================================================================== -->
    <aside class="app-sidebar" id="appSidebar">
        <!-- Logo / Identidad -->
        <div class="sidebar-header">
            <a href="<?= htmlspecialchars(app_url('index.php')) ?>" class="d-flex align-items-center gap-2 text-decoration-none text-reset overflow-hidden w-100">
                <span class="app-brand-badge">
                    <i data-lucide="wallet-cards" style="width: 16px; height: 16px;"></i>
                </span>
                <span class="sidebar-hide-on-collapse fw-bold fs-6 text-truncate" style="letter-spacing: -0.015em;">
                    <?= htmlspecialchars(__('app_name')) ?>
                </span>
            </a>
        </div>

        <!-- Botón de Registro Rápido "+ Transacción" -->
        <div class="sidebar-quick-action">
            <a href="<?= htmlspecialchars(app_url('pages/agregar.php')) ?>" class="btn btn-fintech-primary w-100 d-flex align-items-center justify-content-center gap-1.5 py-1.5 text-nowrap">
                <i data-lucide="plus" style="width: 15px; height: 15px;"></i>
                <span class="sidebar-hide-on-collapse"><?= htmlspecialchars(__('nav_new_tx')) ?></span>
            </a>
        </div>

        <!-- Menú de Navegación Vertical -->
        <nav class="sidebar-nav-group">
            <div class="sidebar-section-title sidebar-hide-on-collapse">General</div>

            <a href="<?= htmlspecialchars(app_url('index.php')) ?>" 
               class="sidebar-nav-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>" 
               title="<?= htmlspecialchars(__('nav_dashboard')) ?>">
                <i data-lucide="layout-dashboard"></i>
                <span class="sidebar-hide-on-collapse"><?= htmlspecialchars(__('nav_dashboard')) ?></span>
            </a>

            <a href="<?= htmlspecialchars(app_url('pages/fijas.php')) ?>" 
               class="sidebar-nav-link <?= ($currentPage === 'fijas.php') ? 'active' : '' ?>" 
               title="<?= htmlspecialchars(__('nav_recurring')) ?>">
                <i data-lucide="calendar-sync"></i>
                <span class="sidebar-hide-on-collapse"><?= htmlspecialchars(__('nav_recurring')) ?></span>
            </a>

            <a href="<?= htmlspecialchars(app_url('pages/cuentas.php')) ?>" 
               class="sidebar-nav-link <?= ($currentPage === 'cuentas.php') ? 'active' : '' ?>" 
               title="<?= htmlspecialchars(__('nav_accounts')) ?>">
                <i data-lucide="credit-card"></i>
                <span class="sidebar-hide-on-collapse"><?= htmlspecialchars(__('nav_accounts')) ?></span>
            </a>

            <div class="sidebar-section-title sidebar-hide-on-collapse mt-3">Operaciones</div>

            <a href="<?= htmlspecialchars(app_url('pages/agregar.php')) ?>" 
               class="sidebar-nav-link <?= ($currentPage === 'agregar.php') ? 'active' : '' ?>" 
               title="<?= htmlspecialchars(__('nav_new_tx')) ?>">
                <i data-lucide="receipt"></i>
                <span class="sidebar-hide-on-collapse"><?= htmlspecialchars(__('nav_new_tx')) ?></span>
            </a>
        </nav>

        <!-- Pie de la Barra Lateral -->
        <div class="sidebar-footer">
            <!-- Botón Colapsar / Expandir (Siempre accesible dentro de la barra) -->
            <button class="btn btn-fintech-ghost w-100 d-flex align-items-center justify-content-center gap-2 py-1 px-2" id="sidebarCollapseBtn" title="Colapsar o expandir menú">
                <i data-lucide="panel-left-close" class="collapse-icon-close" style="width: 15px; height: 15px;"></i>
                <i data-lucide="panel-left-open" class="collapse-icon-open" style="width: 15px; height: 15px;"></i>
                <span class="sidebar-hide-on-collapse" style="font-size: 0.75rem;">Colapsar</span>
            </button>

            <!-- Estado de Base de Datos y Fase -->
            <div class="d-flex align-items-center gap-2 px-2 py-1 small text-muted sidebar-hide-on-collapse">
                <span class="d-inline-block rounded-circle bg-success" style="width: 7px; height: 7px; box-shadow: 0 0 8px rgba(16, 185, 129, 0.7);"></span>
                <span style="font-size: 0.74rem;"><?= function_exists('getDBEnvironmentLabel') ? htmlspecialchars(getDBEnvironmentLabel()) : 'MySQL / MariaDB Activo' ?></span>
                <span class="ms-auto badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 0.62rem;">
                    <?= htmlspecialchars(__('phase_tag')) ?>
                </span>
            </div>

            <!-- Selector de Idioma (Español, Inglés, Francés, Portugués) -->
            <?php
                $langLabels = [
                    'es' => '🇪🇸 Español',
                    'en' => '🇺🇸 English',
                    'fr' => '🇫🇷 Français',
                    'pt' => '🇵🇹 Português'
                ];
                $currentLangDisplay = $langLabels[$activeLang] ?? '🇪🇸 Español';
            ?>
            <div class="dropdown">
                <button class="btn btn-fintech-ghost w-100 d-flex align-items-center justify-content-between py-1 px-2" type="button" id="sidebarLangDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars(__('nav_language')) . ': ' . htmlspecialchars($currentLangDisplay) ?>">
                    <span class="d-flex align-items-center justify-content-center gap-2 lang-btn-inner">
                        <i data-lucide="languages" style="width: 15px; height: 15px;"></i>
                        <span class="sidebar-hide-on-collapse"><?= $currentLangDisplay ?></span>
                    </span>
                    <i data-lucide="chevron-down" style="width: 13px; height: 13px;" class="sidebar-hide-on-collapse"></i>
                </button>
                <ul class="dropdown-menu shadow border-secondary-subtle p-1" aria-labelledby="sidebarLangDropdown">
                    <?php foreach ($langLabels as $code => $label): ?>
                        <li>
                            <form method="POST" action="<?= htmlspecialchars(app_url('controllers/cambiar_idioma.php')) ?>" class="m-0 p-0 lang-switch-form">
                                <input type="hidden" name="lang" value="<?= htmlspecialchars($code) ?>">
                                <button type="submit" class="dropdown-item d-flex align-items-center justify-content-between py-1.5 px-2 rounded-2 <?= ($activeLang === $code) ? 'active' : '' ?>">
                                    <span><?= $label ?></span>
                                    <?php if ($activeLang === $code): ?><i data-lucide="check" style="width: 14px; height: 14px;"></i><?php endif; ?>
                                </button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </aside>

    <!-- ========================================================================
         ÁREA PRINCIPAL DE LA APLICACIÓN
         ======================================================================== -->
    <div class="app-main">
        <!-- Barra de Progreso de Navegación Suave (Linear / YouTube Style) -->
        <div class="fintech-nav-progress" id="navProgressBar"></div>

        <!-- Topbar de Control (Sub-header tipo SaaS) -->
        <header class="app-topbar">
            <!-- Izquierda: Botón Global de Sidebar (Siempre disponible para abrir/cerrar) & Ruta -->
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-fintech-ghost p-1.5 d-flex align-items-center justify-content-center text-secondary" id="globalSidebarToggle" title="Alternar barra lateral" aria-label="Alternar barra lateral">
                    <i data-lucide="panel-left" style="width: 17px; height: 17px;"></i>
                </button>
                <div class="d-flex align-items-center gap-1.5 text-secondary small">
                    <span class="text-muted"><?= htmlspecialchars(__('app_name')) ?></span>
                    <span class="text-muted">/</span>
                    <span class="fw-semibold text-reset" id="topbarCurrentPage"><?= htmlspecialchars($pageTitle ?? __('nav_dashboard')) ?></span>
                </div>
            </div>

            <!-- Derecha: Selector de Tema & Acciones Rápidas (Limpio, sin recuadros distractores) -->
            <div class="d-flex align-items-center gap-2">
                <!-- Selector de Tema (Modo Claro / Oscuro - Instantáneo por CSS) -->
                <button class="btn btn-sm btn-fintech-ghost p-1.5 d-flex align-items-center justify-content-center text-secondary" id="themeToggleBtn" title="Cambiar tema (Claro / Oscuro)" aria-label="Cambiar tema">
                    <i data-lucide="sun" class="theme-icon-sun" style="width: 16px; height: 16px;"></i>
                    <i data-lucide="moon" class="theme-icon-moon" style="width: 16px; height: 16px;"></i>
                </button>

                <!-- Botón de Nueva Transacción -->
                <a href="<?= htmlspecialchars(app_url('pages/agregar.php')) ?>" class="btn btn-sm btn-fintech-primary py-1 px-2.5 d-flex align-items-center gap-1">
                    <i data-lucide="plus" style="width: 14px; height: 14px;"></i>
                    <span class="d-none d-md-inline"><?= htmlspecialchars(__('nav_new_tx')) ?></span>
                </a>
            </div>
        </header>

        <!-- Viewport Scrollable (Maneja su propio scroll internamente) -->
        <main class="app-viewport" id="appViewport">
            <div class="container-fluid px-2 px-md-3 py-2">
                
                <!-- Notificaciones Flash Estilizadas -->
                <?php if ($flashMessage): ?>
                    <div class="alert alert-fintech alert-dismissible fade show d-flex align-items-center gap-2 mb-3 shadow-sm border-start border-4 <?= ($flashMessage['type'] === 'success') ? 'border-start-success' : 'border-start-danger' ?>" role="alert">
                        <i data-lucide="<?= ($flashMessage['type'] === 'success') ? 'check-circle-2' : 'alert-circle' ?>" 
                           style="width: 18px; height: 18px;" 
                           class="<?= ($flashMessage['type'] === 'success') ? 'text-success' : 'text-danger' ?>"></i>
                        <div class="flex-grow-1">
                            <?= htmlspecialchars($flashMessage['message']) ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
