<?php
/**
 * Language Switcher Controller (Clean URLs & Multi-Language Support)
 * Gestor de Presupuesto Personal y Control de Gastos
 * 
 * Supports: es (Spanish), en (English), fr (French), pt (Portuguese)
 * Switches the active language directly in PHP session without
 * polluting the browser URL address bar with query parameters.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/lang.php';

// Read language choice from POST, JSON payload, or GET fallback
$inputLang = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lang'])) {
        $inputLang = trim((string) $_POST['lang']);
    } else {
        // Handle application/json payloads
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['lang'])) {
                $inputLang = trim((string) $data['lang']);
            }
        }
    }
} elseif (isset($_GET['lang'])) {
    $inputLang = trim((string) $_GET['lang']);
}

// Update session language if valid in SUPPORTED_LANGS
if (in_array($inputLang, SUPPORTED_LANGS, true)) {
    $_SESSION['lang'] = $inputLang;
}

// Detect AJAX / Fetch requests
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_GET['ajax']);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'lang'    => $_SESSION['lang'] ?? DEFAULT_LANG
    ]);
    exit;
}

// Clean redirect to referer without any ?lang= parameters
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer)) {
    $parsed = parse_url($referer);
    $targetPath = $parsed['path'] ?? '../index.php';
} else {
    $targetPath = '../index.php';
    $parsed = [];
}

// Preserve other query parameters if any (excluding 'lang')
$queryString = '';
if (!empty($parsed['query'])) {
    parse_str($parsed['query'], $queryParams);
    unset($queryParams['lang']);
    if (!empty($queryParams)) {
        $queryString = '?' . http_build_query($queryParams);
    }
}

// Redirect cleanly back to the same page
header('Location: ' . $targetPath . $queryString);
exit;
