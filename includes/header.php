<?php
/**
 * Header Template Component
 * Gestor de Presupuesto Personal y Control de Gastos
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/lang.php';

$activeLang = current_lang();
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$flashMessage = get_flash();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($activeLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? __('app_name')) ?> - <?= htmlspecialchars(__('app_subtitle')) ?></title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --bs-font-sans-serif: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --brand-primary: #0f172a;
            --brand-accent: #2563eb;
            --income-color: #10b981;
            --expense-color: #ef4444;
        }

        body {
            font-family: var(--bs-font-sans-serif);
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1 0 auto;
        }

        .navbar-brand-custom {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
            color: #ffffff !important;
        }

        .navbar-brand-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 8px;
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);
        }

        .card-stat {
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 14px;
            background: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        }

        .icon-bubble {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .icon-bubble-income {
            background-color: #ecfdf5;
            color: #059669;
        }

        .icon-bubble-expense {
            background-color: #fef2f2;
            color: #dc2626;
        }

        .icon-bubble-balance {
            background-color: #eff6ff;
            color: #2563eb;
        }

        .badge-income {
            background-color: #d1fae5;
            color: #065f46;
            font-weight: 600;
            border-radius: 6px;
            padding: 0.35em 0.65em;
        }

        .badge-expense {
            background-color: #fee2e2;
            color: #991b1b;
            font-weight: 600;
            border-radius: 6px;
            padding: 0.35em 0.65em;
        }

        .badge-category {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 500;
            border-radius: 6px;
            font-size: 0.82rem;
            padding: 0.3em 0.6em;
            border: 1px solid #e2e8f0;
        }

        .table th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            color: #64748b;
            border-top: none;
        }

        .table-custom {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom tbody tr {
            transition: background-color 0.15s ease;
        }

        .table-custom tbody tr:hover {
            background-color: #f8fafc;
        }

        .lang-active {
            font-weight: 600;
            color: #2563eb !important;
        }

        .footer-custom {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand navbar-brand-custom d-flex align-items-center gap-2" href="index.php">
                <span class="navbar-brand-icon">
                    <i class="bi bi-wallet2"></i>
                </span>
                <span><?= htmlspecialchars(__('app_name')) ?></span>
                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle ms-1" style="font-size: 0.65rem;">
                    <?= htmlspecialchars(__('phase_tag')) ?>
                </span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'index.php') ? 'active fw-semibold' : '' ?>" href="index.php">
                            <i class="bi bi-grid-1x2-fill me-1"></i> <?= htmlspecialchars(__('nav_dashboard')) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'agregar.php') ? 'active fw-semibold' : '' ?>" href="agregar.php">
                            <i class="bi bi-plus-circle-fill me-1"></i> <?= htmlspecialchars(__('nav_new_tx')) ?>
                        </a>
                    </li>
                </ul>

                <!-- Language Switcher Dropdown -->
                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-2 px-3 py-1 rounded-pill" type="button" id="langDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-translate"></i>
                            <span><?= ($activeLang === 'es') ? '🇪🇸 Español' : '🇺🇸 English' ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="langDropdown">
                            <li><h6 class="dropdown-header small text-uppercase"><?= htmlspecialchars(__('nav_language')) ?></h6></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center justify-content-between py-2 <?= ($activeLang === 'en') ? 'lang-active bg-light' : '' ?>" href="<?= htmlspecialchars(lang_url('en')) ?>">
                                    <span>🇺🇸 <?= htmlspecialchars(__('lang_en')) ?></span>
                                    <?php if ($activeLang === 'en'): ?>
                                        <i class="bi bi-check2 text-primary"></i>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center justify-content-between py-2 <?= ($activeLang === 'es') ? 'lang-active bg-light' : '' ?>" href="<?= htmlspecialchars(lang_url('es')) ?>">
                                    <span>🇪🇸 <?= htmlspecialchars(__('lang_es')) ?></span>
                                    <?php if ($activeLang === 'es'): ?>
                                        <i class="bi bi-check2 text-primary"></i>
                                    <?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <a href="agregar.php" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 shadow-sm d-none d-sm-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-lg"></i>
                        <span><?= htmlspecialchars(__('nav_new_tx')) ?></span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="py-4">
        <div class="container">
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi <?= ($flashMessage['type'] === 'success') ? 'bi-check-circle-fill text-success fs-5' : 'bi-exclamation-triangle-fill text-danger fs-5' ?>"></i>
                    <div class="flex-grow-1">
                        <?= htmlspecialchars($flashMessage['message']) ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
